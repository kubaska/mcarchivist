<?php

namespace App\Jobs\Middleware;

use App\Services\JobService;
use Illuminate\Bus\Batch;

// Since there is no event that would indicate Batch has started processing jobs, we have to make one ourselves.
class UpdatesBatchStatus
{
    /**
     * Process the job.
     *
     * @param  mixed  $job
     * @param  callable  $next
     * @return mixed
     */
    public function handle($job, $next)
    {
        /** @var Batch $batch */
        if (method_exists($job, 'batch') && $batch = $job->batch()) {
            JobService::onBatchProgress($batch);
        }

        $next($job);
    }
}
