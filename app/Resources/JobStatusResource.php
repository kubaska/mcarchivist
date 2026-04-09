<?php

namespace App\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobStatusResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'original_id' => $this->original_id,
            'uuid' => $this->uuid,
            'frontend_id' => $this->frontend_id,
            'job_type' => $this->job_type,
            'state' => $this->state,
            'name' => $this->name,
            'cancellable' => $this->canBeCancelled(),
            'details' => $this->details,
            'exception' => $this->exception
//            'updated_at' => $this->updated_at
        ];
    }
}
