<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DocumentNumberGenerator
{
    public function next(CarbonImmutable $date): string
    {
        $utcDate = $date->setTimezone('UTC');
        $sequenceDate = $utcDate->toDateString();

        $sequence = DB::selectOne(
            'INSERT INTO document_number_sequences (sequence_date, last_number)
             VALUES (?, 1)
             ON CONFLICT (sequence_date)
             DO UPDATE SET last_number = document_number_sequences.last_number + 1
             RETURNING last_number',
            [$sequenceDate],
        );

        return sprintf('RCV-%s-%04d', $utcDate->format('Ymd'), (int) $sequence->last_number);
    }
}
