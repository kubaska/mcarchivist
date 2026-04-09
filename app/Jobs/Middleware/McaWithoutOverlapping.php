<?php

namespace App\Jobs\Middleware;

use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\Middleware\WithoutOverlapping;

// WithoutOverlapping middleware that accounts for jobs dispatched synchronously.
// From https://stackoverflow.com/questions/77144664/laravel-jobs-without-overlap-in-sync-mode
class McaWithoutOverlapping extends WithoutOverlapping
{
    public function handle($job, $next)
    {
        $lock = Container::getInstance()->make(Cache::class)->lock(
            $this->getLockKey($job), $this->expiresAfter
        );

        if ($lock->get()) {
            try {
                $next($job);
            } finally {
                $lock->release();
            }
        }
        elseif ($job->connection === 'sync') {
            do {
                sleep(1);
            } while (!$lock->get());

            try {
                $next($job);
            } finally {
                $lock->release();
            }
        }
        elseif (! is_null($this->releaseAfter)) {
            $job->release($this->releaseAfter);
        }
    }
}
