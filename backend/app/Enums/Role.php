<?php

namespace App\Enums;

enum Role: string
{
    case Operator = 'OPERATOR';
    case Supervisor = 'SUPERVISOR';
}
