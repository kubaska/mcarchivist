<?php

namespace App\Enums;

enum JobState: int
{
    case CREATED = 0;
    case RUNNING = 1;
    case FINISHED = 2;
    case FAILED = 3;
    case CANCELLED = 4;

    public function canBeCancelled(): bool
    {
        return match($this) {
            self::CREATED, self::FAILED => true,
            default => false
        };
    }
}
