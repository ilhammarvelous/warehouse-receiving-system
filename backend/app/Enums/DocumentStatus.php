<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'DRAFT';
    case Open = 'OPEN';
    case PartiallyReceived = 'PARTIALLY_RECEIVED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
