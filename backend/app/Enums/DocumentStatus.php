<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'DRAFT';
    case Open = 'OPEN';
    case PartiallyReceived = 'PARTIALLY_RECEIVED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    public function transitionTo(DocumentAction $action): ?self
    {
        return match ($action) {
            DocumentAction::Confirm => $this === self::Draft ? self::Open : null,
            DocumentAction::Complete => $this === self::PartiallyReceived ? self::Completed : null,
            DocumentAction::Cancel => match ($this) {
                self::Draft, self::Open => self::Cancelled,
                default => null,
            },
        };
    }
}
