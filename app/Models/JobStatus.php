<?php

namespace App\Models;

use App\Enums\JobState;
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
}
