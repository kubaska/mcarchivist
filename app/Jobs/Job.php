<?php

namespace App\Jobs;

use App\Exceptions\RateLimitedApiException;
use App\Jobs\Middleware\UpdatesBatchStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\SerializesModels;

abstract class Job implements ShouldQueue
{
    /*
    |--------------------------------------------------------------------------
    | Queueable Jobs
    |--------------------------------------------------------------------------
    |
    | This job base class provides a central location to place any logic that
    | is shared across all of your jobs. The trait included with the class
    | provides access to the "queueOn" and "delay" queue helper methods.
    |
    */

    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;
    public int $maxExceptions = 1;
    public int $timeout = 3600;
    public bool $failOnTimeout = true;
    public int $backoff = 10;
    public bool $deleteWhenMissingModels = true;

    public function middleware(): array
    {
        return [
            (new ThrottlesExceptions(1, 60))
                ->backoff(1)
                ->when(fn(\Throwable $e) => $e instanceof RateLimitedApiException || $e instanceof ConnectionException),
            new SkipIfBatchCancelled,
            new UpdatesBatchStatus
        ];
    }
}
