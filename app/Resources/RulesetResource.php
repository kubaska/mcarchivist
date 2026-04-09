<?php

namespace App\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RulesetResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'custom' => $this->custom,
            'rules' => ArchiveRuleResource::collection($this->whenLoaded('archive_rules')),
            'created_at' => $this->created_at
        ];
    }
}
