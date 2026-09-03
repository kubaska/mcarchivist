<?php

namespace App\Models;

use App\Enums\JobState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JobStatus extends Model
{
    protected $guarded = [];

    protected $casts = [
        'state' => JobState::class
    ];

    public function canBeCancelled(): bool
    {
        return $this->state->canBeCancelled() && is_null($this->batch_id);
    }

    /**
     * Include job exception in result set.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithException(Builder $query): Builder
    {
        return $query->leftJoin('failed_jobs', 'failed_jobs.uuid', '=', 'job_statuses.uuid')
            ->select(['job_statuses.*', 'failed_jobs.exception']);
    }
}
