<?php

namespace App\Enums;

enum FileQualifier: int
{
    case PRIMARY_ONLY = 0;
    case ALL = 1;

    public function name(): string
    {
        return match ($this) {
            self::PRIMARY_ONLY => 'Primary only',
            self::ALL => 'All'
        };
    }
}
