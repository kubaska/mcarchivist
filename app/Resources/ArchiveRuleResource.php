<?php

namespace App\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArchiveRuleResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'loader_id' => $this->loader_id ?? '*',
            'game_version_from' => $this->game_version_from,
            'game_version_to' => $this->game_version_to,
            'with_snapshots' => $this->with_snapshots,
            'release_type' => $this->release_type ?? '*',
            'release_type_priority' => $this->release_type_priority,
            'count' => $this->count,
            'sorting' => $this->sorting,
            'dependencies' => $this->dependencies,
            'all_files' => $this->all_files
        ];
    }
}
