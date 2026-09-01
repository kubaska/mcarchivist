<?php

namespace App\Jobs\Middleware;

use App\Enums\JobState;
use App\Enums\JobType;
use App\Models\JobStatus;

class Exclusive
{
    /**
     * Process an exclusive job.
     * Exclusive meaning it can be the only one processing in the queue at the moment.
     *
     * @param  mixed  $job
     * @param  callable  $next
     */
    public function handle($job, $next)
    {
        if (method_exists($job, 'getJobType') && $job->getJobType() === JobType::EXCLUSIVE) {
            if (static::isAnyJobRunning($job->job->uuid())) {
                return $job->release(10);
            }
        } else {
            if ($this->isExclusiveJobQueuedOrRunning()) {
                return $job->release(60);
            }
        }

        $next($job);
    }

    public static function isAnyJobRunning(string $exceptUuid): bool
    {
        return JobStatus::query()
            ->where('state', JobState::RUNNING)
            ->whereNot('uuid', $exceptUuid)
            ->exists();
    }

    protected function isExclusiveJobQueuedOrRunning(): bool
    {
        return JobStatus::query()
            ->where('job_type', JobType::EXCLUSIVE)
            ->whereIn('state', [JobState::CREATED, JobState::RUNNING])
            ->exists();
    }
}
