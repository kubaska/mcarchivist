<?php

namespace App\API\DTO;

use App\Models\MasterProject;
use App\Models\Project;
use App\Resources\ArchiveRuleResource;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

class MasterProjectDTO extends DTO implements Arrayable
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly AnonymousResourceCollection $archiveRules,
        public readonly ?Collection $projects
    )
    {
    }

    public static function fromLocal(MasterProject $mp)
    {
        return new self(
            $mp->getKey(),
            $mp->name,
            ArchiveRuleResource::collection($mp->archive_rules),
            $mp->relationLoaded('projects')
                ? $mp->projects->map(fn(Project $p) => ProjectDTO::fromLocal($mp, $p))
                : null
        );
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'archive_rules' => $this->archiveRules,
            'projects' => $this->projects
        ];
    }
}
