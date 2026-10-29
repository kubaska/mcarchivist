<?php

namespace App\Enums;

enum JobState: int
{
    case CREATED = 0;
    case RUNNING = 1;
    case FINISHED = 2;
    case FAILED = 3;
    case CANCELLED = 4;
    case FINISHED_WITH_WARNINGS = 5;
    case FINISHED_WITH_ERRORS = 6;

    public function canBeCancelled(): bool
    {
        return match($this) {
            self::CREATED, self::FAILED, self::FINISHED_WITH_WARNINGS, self::FINISHED_WITH_ERRORS => true,
            default => false
        };
    }
}
