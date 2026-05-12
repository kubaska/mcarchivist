<?php

namespace App\API;

use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;

/**
 * Greedy caching strategy that caches only successful responses
 */
class McaCachingStrategy extends GreedyCacheStrategy
{
    protected $statusAccepted = [
        200 => 200,
        203 => 203,
        204 => 204
    ];
}
