<?php

namespace Tests\Concurrency;

use App\Enums\DocumentStatus;
use App\Enums\ErrorCode;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ReceivingDocument;
use App\Models\ReceivingItem;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ReceivingConcurrencyTest extends TestCase
{
    private string $baseDatabase;

    private string $concurrencyDatabase;

    private bool $databaseCreated = false;

    private int $documentNumber = 0;

    private User $operator;

    private User $supervisor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDatabase = config('database.connections.pgsql.database');
        $this->concurrencyDatabase = 'warehouse_concurrency_'.Str::lower(Str::random(12));

        try {
            DB::statement("CREATE DATABASE \"{$this->concurrencyDatabase}\"");
            $this->databaseCreated = true;
            config(['database.connections.pgsql.database' => $this->concurrencyDatabase]);
            DB::purge('pgsql');

            $exitCode = Artisan::call('migrate:fresh', ['--force' => true]);
            if ($exitCode !== 0) {
                throw new RuntimeException('Could not migrate the isolated concurrency database: '.Artisan::output());
            }

            $this->operator = User::factory()->create(['role' => 'OPERATOR']);
            $this->supervisor = User::factory()->create(['role' => 'SUPERVISOR']);
            $this->product = $this->makeProduct();
        } catch (Throwable $exception) {
            $this->dropConcurrencyDatabase();

            throw $exception;
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->databaseCreated) {
                DB::statement(
                    'TRUNCATE TABLE idempotency_keys, inventory_transactions, receipts, receiving_items, '
                    .'receiving_documents, inventory, products, personal_access_tokens, document_number_sequences, users '
                    .'RESTART IDENTITY CASCADE',
                );
            }
        } finally {
            $this->dropConcurrencyDatabase();
            parent::tearDown();
        }
    }

    public function test_us_11_br_07_br_11_br_13_br_15_br_17_parallel_receipts_and_cancel_preserve_integrity(): void
    {
        for ($run = 1; $run <= 3; $run++) {
            $this->assertConcurrentOverReceivingIsSerialized($run);
            $this->assertConcurrentIdempotentRequestsReplay($run);
            $this->assertReceiptAndCancelCannotBothWin($run);
            $this->assertParallelProductReceiptsKeepStockLedgerConsistent($run);
            $this->assertIntegrityQueriesReturnNoRows();
        }
    }

    private function assertConcurrentOverReceivingIsSerialized(int $run): void
    {
        [$document, $item] = $this->makeOpenDocument(5);
        $stockBefore = $this->product->inventory()->value('quantity_on_hand');
        $results = $this->runWorkers([
            $this->receiptWorker($document, $item, 5, "over-receive-{$run}-first"),
            $this->receiptWorker($document, $item, 5, "over-receive-{$run}-second"),
        ]);

        $successes = array_filter($results, fn (array $result): bool => $result['status'] === 201);
        $rejections = array_filter(
            $results,
            fn (array $result): bool => $result['status'] === 422
                && ($result['body']['error']['code'] ?? null) === ErrorCode::QuantityExceedsRemaining->value,
        );

        $this->assertCount(1, $successes, "Run {$run}: one concurrent receipt must succeed.");
        $this->assertCount(1, $rejections, "Run {$run}: the other receipt must exceed the remaining quantity.");
        $this->assertSame(5, $item->fresh()->received_quantity);
        $this->assertSame((int) $stockBefore + 5, $this->product->inventory()->value('quantity_on_hand'));
        $this->assertSame(1, DB::table('receipts')->where('receiving_item_id', $item->id)->count());
        $this->assertSame(1, DB::table('inventory_transactions')->where('receiving_item_id', $item->id)->count());
    }

    private function assertConcurrentIdempotentRequestsReplay(int $run): void
    {
        [$document, $item] = $this->makeOpenDocument(5);
        $stockBefore = $this->product->inventory()->value('quantity_on_hand');
        $key = "same-key-concurrency-{$run}";
        $results = $this->runWorkers([
            $this->receiptWorker($document, $item, 5, $key),
            $this->receiptWorker($document, $item, 5, $key),
        ]);

        $this->assertSame([201, 201], array_column($results, 'status'), "Run {$run}: both idempotent requests must succeed.");
        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => ! $result['replayed'])));
        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['replayed'])));
        $this->assertSame(
            $this->canonicalize($results[0]['body']),
            $this->canonicalize($results[1]['body']),
            "Run {$run}: concurrent idempotent requests must receive the same stored body.",
        );
        $this->assertSame(5, $item->fresh()->received_quantity);
        $this->assertSame((int) $stockBefore + 5, $this->product->inventory()->value('quantity_on_hand'));
        $this->assertSame(1, DB::table('receipts')->where('receiving_item_id', $item->id)->count());
        $this->assertSame(1, DB::table('inventory_transactions')->where('receiving_item_id', $item->id)->count());
    }

    private function assertReceiptAndCancelCannotBothWin(int $run): void
    {
        [$document, $item] = $this->makeOpenDocument(5);
        $stockBefore = $this->product->inventory()->value('quantity_on_hand');
        $results = $this->runWorkers([
            $this->receiptWorker($document, $item, 5, "cancel-race-{$run}"),
            [
                'action' => 'cancel',
                'database' => $this->concurrencyDatabase,
                'user_id' => $this->supervisor->id,
                'document_id' => $document->id,
                'item_id' => $item->id,
                'quantity' => 0,
                'key' => '',
            ],
        ]);

        $receiptResult = $results[0];
        $cancelResult = $results[1];
        $status = $document->fresh()->status;

        if ($cancelResult['status'] === 200) {
            $this->assertSame(DocumentStatus::Cancelled, $status);
            $this->assertSame(409, $receiptResult['status']);
            $this->assertSame(ErrorCode::DocumentNotReceivable->value, $receiptResult['body']['error']['code']);
            $this->assertSame(0, $item->fresh()->received_quantity);
            $this->assertSame(0, DB::table('receipts')->where('receiving_item_id', $item->id)->count());
            $this->assertSame($stockBefore, $this->product->inventory()->value('quantity_on_hand'));
        } else {
            $this->assertSame(409, $cancelResult['status']);
            $this->assertSame(ErrorCode::DocumentHasReceipts->value, $cancelResult['body']['error']['code']);
            $this->assertSame(201, $receiptResult['status']);
            $this->assertSame(DocumentStatus::PartiallyReceived, $status);
            $this->assertSame(5, $item->fresh()->received_quantity);
            $this->assertSame(1, DB::table('receipts')->where('receiving_item_id', $item->id)->count());
            $this->assertSame((int) $stockBefore + 5, $this->product->inventory()->value('quantity_on_hand'));
        }

        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => in_array($result['status'], [200, 201], true))));
        $this->assertSame(
            DB::table('receipts')->where('receiving_item_id', $item->id)->count(),
            DB::table('inventory_transactions')->where('receiving_item_id', $item->id)->count(),
        );
    }

    private function assertParallelProductReceiptsKeepStockLedgerConsistent(int $run): void
    {
        $workers = [];
        $items = [];
        $receiptCount = 8;

        for ($index = 1; $index <= $receiptCount; $index++) {
            [$document, $item] = $this->makeOpenDocument(3);
            $items[] = $item;
            $workers[] = $this->receiptWorker(
                $document,
                $item,
                1,
                "parallel-stock-{$run}-{$index}",
            );
        }

        $results = $this->runWorkers($workers);

        $this->assertSame(
            array_fill(0, $receiptCount, 201),
            array_column($results, 'status'),
            "Run {$run}: each receipt on a different document must succeed without deadlocks.",
        );

        foreach ($items as $item) {
            $this->assertSame(1, $item->fresh()->received_quantity);
        }

        $itemIds = array_map(fn (ReceivingItem $item): int => $item->id, $items);
        $this->assertSame(
            $receiptCount,
            DB::table('receipts')->whereIn('receiving_item_id', $itemIds)->count(),
        );
        $this->assertSame(
            $receiptCount,
            DB::table('inventory_transactions')->whereIn('receiving_item_id', $itemIds)->count(),
        );

        $expectedStock = DB::table('inventory_transactions')
            ->where('product_id', $this->product->id)
            ->sum('quantity');
        $actualStock = $this->product->inventory()->value('quantity_on_hand');

        $this->assertSame((int) $expectedStock, $actualStock);
    }

    private function assertIntegrityQueriesReturnNoRows(): void
    {
        $queries = [
            'SELECT i.product_id, i.quantity_on_hand, COALESCE(SUM(t.quantity), 0) AS ledger_sum
             FROM inventory i
             LEFT JOIN inventory_transactions t ON t.product_id = i.product_id
             GROUP BY i.product_id, i.quantity_on_hand
             HAVING i.quantity_on_hand <> COALESCE(SUM(t.quantity), 0)',
            'SELECT ri.id, ri.received_quantity, COALESCE(SUM(r.quantity), 0) AS receipts_sum
             FROM receiving_items ri
             LEFT JOIN receipts r ON r.receiving_item_id = ri.id
             GROUP BY ri.id, ri.received_quantity
             HAVING ri.received_quantity <> COALESCE(SUM(r.quantity), 0)',
            'SELECT id FROM receiving_items WHERE received_quantity > expected_quantity',
            'SELECT r.id
             FROM receipts r
             LEFT JOIN inventory_transactions t ON t.receipt_id = r.id
             WHERE t.id IS NULL',
            'SELECT *
             FROM (
                 SELECT id, product_id, balance_after,
                        SUM(quantity) OVER (PARTITION BY product_id ORDER BY id) AS expected_balance
                 FROM inventory_transactions
             ) x
             WHERE balance_after <> expected_balance',
            "SELECT d.id
             FROM receiving_documents d
             JOIN receiving_items ri ON ri.receiving_document_id = d.id
             WHERE d.status = 'COMPLETED' AND ri.received_quantity < ri.expected_quantity",
        ];

        foreach ($queries as $index => $query) {
            $this->assertSame([], DB::select($query), 'Database-design integrity query '.($index + 1).' must return no rows.');
        }
    }

    /**
     * @param  array<int, array{action: string, database: string, user_id: int, document_id: int, item_id: int, quantity: int, key: string}>  $workers
     * @return array<int, array{status: int, body: array<string, mixed>, replayed: bool}>
     */
    private function runWorkers(array $workers): array
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'warehouse-concurrency-'.Str::lower(Str::random(12));
        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('Could not create the concurrency worker barrier directory.');
        }

        $barrierPath = $directory.DIRECTORY_SEPARATOR.'start';
        $processes = [];

        try {
            foreach ($workers as $index => $worker) {
                $readyPath = $directory.DIRECTORY_SEPARATOR."ready-{$index}";
                $pipes = [];
                $process = proc_open([
                    PHP_BINARY,
                    __DIR__.DIRECTORY_SEPARATOR.'receipt-worker.php',
                    $worker['action'],
                    $worker['database'],
                    (string) $worker['user_id'],
                    (string) $worker['document_id'],
                    (string) $worker['item_id'],
                    (string) $worker['quantity'],
                    $worker['key'],
                    $readyPath,
                    $barrierPath,
                ], [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ], $pipes, base_path());

                if (! is_resource($process)) {
                    throw new RuntimeException('Could not start a concurrent receipt worker.');
                }

                fclose($pipes[0]);
                $processes[] = [
                    'process' => $process,
                    'stdout' => $pipes[1],
                    'stderr' => $pipes[2],
                    'ready_path' => $readyPath,
                ];
            }

            $deadline = microtime(true) + 30;
            while (count(glob($directory.DIRECTORY_SEPARATOR.'ready-*') ?: []) < count($workers)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Concurrent receipt workers did not reach the start barrier.');
                }

                usleep(10_000);
            }

            if (file_put_contents($barrierPath, 'start', LOCK_EX) === false) {
                throw new RuntimeException('Could not release concurrent receipt workers.');
            }

            $results = [];
            foreach ($processes as &$workerProcess) {
                $output = stream_get_contents($workerProcess['stdout']);
                $errors = stream_get_contents($workerProcess['stderr']);
                fclose($workerProcess['stdout']);
                fclose($workerProcess['stderr']);
                $exitCode = proc_close($workerProcess['process']);
                $workerProcess = null;

                if ($exitCode !== 0) {
                    throw new RuntimeException("Concurrent receipt worker failed: {$errors}");
                }

                try {
                    $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    throw new RuntimeException(
                        'Concurrent receipt worker returned invalid JSON. stdout='
                        .var_export($output, true).' stderr='.var_export($errors, true),
                        previous: $exception,
                    );
                }
                $this->assertIsArray($result);
                $this->assertArrayHasKey('status', $result);
                $this->assertArrayHasKey('body', $result);
                $this->assertArrayHasKey('replayed', $result);
                $results[] = $result;
            }
            unset($workerProcess);

            return $results;
        } finally {
            if (! file_exists($barrierPath)) {
                file_put_contents($barrierPath, 'start', LOCK_EX);
            }

            foreach ($processes as $workerProcess) {
                if ($workerProcess === null) {
                    continue;
                }

                proc_terminate($workerProcess['process']);
                fclose($workerProcess['stdout']);
                fclose($workerProcess['stderr']);
                proc_close($workerProcess['process']);
            }

            foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }

    /**
     * @return array{action: string, database: string, user_id: int, document_id: int, item_id: int, quantity: int, key: string}
     */
    private function receiptWorker(
        ReceivingDocument $document,
        ReceivingItem $item,
        int $quantity,
        string $key,
    ): array {
        return [
            'action' => 'receipt',
            'database' => $this->concurrencyDatabase,
            'user_id' => $this->operator->id,
            'document_id' => $document->id,
            'item_id' => $item->id,
            'quantity' => $quantity,
            'key' => $key,
        ];
    }

    /**
     * @return array{0: ReceivingDocument, 1: ReceivingItem}
     */
    private function makeOpenDocument(int $expectedQuantity): array
    {
        $this->documentNumber++;
        $document = ReceivingDocument::query()->create([
            'document_number' => sprintf('RCV-20991231-%04d', $this->documentNumber),
            'supplier_name' => 'Concurrency supplier',
            'expected_date' => '2099-12-31',
            'status' => DocumentStatus::Open,
            'created_by' => $this->supervisor->id,
            'status_changed_by' => $this->supervisor->id,
            'status_changed_at' => now('UTC'),
        ]);

        $item = $document->items()->create([
            'product_id' => $this->product->id,
            'expected_quantity' => $expectedQuantity,
            'received_quantity' => 0,
        ]);

        return [$document, $item];
    }

    private function makeProduct(): Product
    {
        $product = Product::query()->create([
            'sku' => 'CONCURRENCY-'.Str::upper(Str::random(10)),
            'name' => 'Concurrency test product',
            'created_by' => $this->supervisor->id,
        ]);
        Inventory::query()->create([
            'product_id' => $product->id,
            'quantity_on_hand' => 0,
        ]);

        return $product;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    private function dropConcurrencyDatabase(): void
    {
        if (! $this->databaseCreated) {
            return;
        }

        DB::purge('pgsql');
        config(['database.connections.pgsql.database' => $this->baseDatabase]);
        DB::connection('pgsql')->getPdo();
        DB::statement("DROP DATABASE IF EXISTS \"{$this->concurrencyDatabase}\"");
        $this->databaseCreated = false;
    }
}
