<?php

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\ReceivingDocumentService;
use App\Services\ReceivingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$basePath = dirname(__DIR__, 2);

require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $action, $database, $userId, $documentId, $itemId, $quantity, $key, $readyPath, $barrierPath] = $argv;

config(['database.connections.pgsql.database' => $database]);
DB::purge('pgsql');
DB::connection('pgsql')->getPdo();

$user = User::query()->findOrFail((int) $userId);
file_put_contents($readyPath, 'ready', LOCK_EX);

$deadline = microtime(true) + 30;
while (! file_exists($barrierPath)) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('The concurrent worker start barrier timed out.');
    }

    usleep(10_000);
}

try {
    if ($action === 'receipt') {
        $result = app(ReceivingService::class)->recordReceipt(
            (int) $documentId,
            (int) $itemId,
            (int) $quantity,
            $key,
            $user,
        );

        $response = [
            'status' => $result['status'],
            'body' => ['data' => $result['body']],
            'replayed' => $result['replayed'],
        ];
    } elseif ($action === 'cancel') {
        $document = app(ReceivingDocumentService::class)->cancel((int) $documentId, $user);
        $response = [
            'status' => 200,
            'body' => ['data' => ['status' => $document->status->value]],
            'replayed' => false,
        ];
    } else {
        throw new InvalidArgumentException('Unsupported concurrent worker action.');
    }
} catch (ApiException $exception) {
    $response = [
        'status' => $exception->status,
        'body' => [
            'error' => [
                'code' => $exception->errorCode->value,
                'message' => $exception->getMessage(),
                'details' => $exception->details,
            ],
        ],
        'replayed' => false,
    ];
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->__toString());
    exit(1);
}

echo json_encode($response, JSON_THROW_ON_ERROR);
