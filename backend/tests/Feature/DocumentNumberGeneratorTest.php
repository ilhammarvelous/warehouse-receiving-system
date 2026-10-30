<?php

namespace Tests\Feature;

use App\Services\DocumentNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentNumberGeneratorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_us_04_fr_doc_2_parallel_document_creation_assigns_unique_numbers(): void
    {
        $date = '2099-12-31';
        $processDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'document-number-'.Str::uuid();
        $this->assertTrue(mkdir($processDirectory, 0700));

        $barrierPath = $processDirectory.DIRECTORY_SEPARATOR.'start';
        $processes = [];
        $outputs = [];

        try {
            $this->runGeneratorProcess('cleanup', $date);

            for ($index = 0; $index < 4; $index++) {
                $readyPath = $processDirectory.DIRECTORY_SEPARATOR."ready-{$index}";
                $processes[] = $this->startGeneratorProcess($date, $readyPath, $barrierPath);
            }

            $deadline = microtime(true) + 15;
            while (count(glob($processDirectory.DIRECTORY_SEPARATOR.'ready-*') ?: []) < 4) {
                if (microtime(true) >= $deadline) {
                    $this->fail('The document-number workers did not reach the parallel start barrier.');
                }

                usleep(10_000);
            }

            $this->assertNotFalse(file_put_contents($barrierPath, 'start'));

            foreach ($processes as &$process) {
                $outputs[] = stream_get_contents($process['stdout']);
                $errors = stream_get_contents($process['stderr']);
                fclose($process['stdout']);
                fclose($process['stderr']);
                $exitCode = proc_close($process['process']);
                $process = null;

                $this->assertSame(0, $exitCode, $errors);
            }
            unset($process);

            sort($outputs);
            $this->assertSame([
                'RCV-20991231-0001',
                'RCV-20991231-0002',
                'RCV-20991231-0003',
                'RCV-20991231-0004',
            ], array_map('trim', $outputs));
        } finally {
            if (! file_exists($barrierPath)) {
                file_put_contents($barrierPath, 'start');
            }

            foreach ($processes as $process) {
                if ($process === null) {
                    continue;
                }

                fclose($process['stdout']);
                fclose($process['stderr']);
                proc_close($process['process']);
            }

            $this->runGeneratorProcess('cleanup', $date);

            foreach (glob($processDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($processDirectory);
        }
    }

    public function test_br_19_document_numbers_use_independent_atomic_daily_sequences(): void
    {
        $generator = app(DocumentNumberGenerator::class);

        $firstDateFirstNumber = $generator->next(CarbonImmutable::parse('2026-11-02 12:00:00', 'UTC'));
        $secondDateFirstNumber = $generator->next(CarbonImmutable::parse('2026-11-03 12:00:00', 'UTC'));
        $firstDateSecondNumber = $generator->next(CarbonImmutable::parse('2026-11-02 13:00:00', 'UTC'));

        $this->assertSame('RCV-20261102-0001', $firstDateFirstNumber);
        $this->assertSame('RCV-20261103-0001', $secondDateFirstNumber);
        $this->assertSame('RCV-20261102-0002', $firstDateSecondNumber);
    }

    /**
     * @return array{process: resource, stdout: resource, stderr: resource}
     */
    private function startGeneratorProcess(string $date, string $readyPath, string $barrierPath): array
    {
        $pipes = [];
        $process = proc_open([
            PHP_BINARY,
            base_path('tests/Support/document-number-process.php'),
            'generate',
            $date,
            $readyPath,
            $barrierPath,
        ], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, base_path());

        $this->assertIsResource($process);
        fclose($pipes[0]);

        return [
            'process' => $process,
            'stdout' => $pipes[1],
            'stderr' => $pipes[2],
        ];
    }

    private function runGeneratorProcess(string $action, string $date): void
    {
        $process = proc_open([
            PHP_BINARY,
            base_path('tests/Support/document-number-process.php'),
            $action,
            $date,
        ], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, base_path());

        $this->assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $errors.$output);
    }
}
