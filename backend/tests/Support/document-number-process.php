<?php

use App\Services\DocumentNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$basePath = dirname(__DIR__, 2);

require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$action = $argv[1];
$date = CarbonImmutable::parse($argv[2], 'UTC');

if ($action === 'cleanup') {
    DB::table('document_number_sequences')
        ->where('sequence_date', $date->toDateString())
        ->delete();

    exit(0);
}

if ($action !== 'generate') {
    throw new InvalidArgumentException('Unsupported document-number process action.');
}

file_put_contents($argv[3], 'ready');

$deadline = microtime(true) + 15;
while (! file_exists($argv[4])) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('The document-number start barrier timed out.');
    }

    usleep(10_000);
}

echo DB::transaction(fn (): string => app(DocumentNumberGenerator::class)->next($date));
