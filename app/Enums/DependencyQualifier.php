<?php

namespace App\Enums;

enum DependencyQualifier: int
{
    case NONE = 0;
    case REQUIRED_ONLY = 1;
    case ALL = 2;

    /**
     * Compare priority of qualifiers.
     *
     * @param DependencyQualifier $qualifier
     * @return int `1` if compared has more priority, `0` if equal, `-1` if compared has less priority
     */
    public function comparePriority(self $qualifier): int
    {
        return $qualifier->value <=> $this->value;
    }
}
