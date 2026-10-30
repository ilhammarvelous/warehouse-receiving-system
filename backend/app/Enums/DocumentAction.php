<?php

namespace App\Enums;

enum DocumentAction: string
{
    case Confirm = 'confirm';
    case Complete = 'complete';
    case Cancel = 'cancel';

    public function pastParticiple(): string
    {
        return match ($this) {
            self::Confirm => 'confirmed',
            self::Complete => 'completed',
            self::Cancel => 'cancelled',
        };
    }
}
