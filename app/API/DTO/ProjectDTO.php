<?php

namespace App\API\DTO;

use App\Enums\ProjectDependencyType;
use App\Enums\EProjectType;
use App\Models\Author;
use App\Models\Category;
use App\Models\MasterProject;
use App\Models\Project;
use App\Models\ProjectType;
use App\Resources\ArchiveRuleResource;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class ProjectDTO extends DTO implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly string $remoteId,
        public ?int $projectId,
        public readonly string $name,
        public readonly string $summary,
        public readonly ?string $description,
        public readonly ?string $logo,
        public readonly array $gallery,
        public readonly string $projectUrl,
        public readonly ?int $downloads,
        protected readonly ?array $gameVersions,
        public readonly ?Collection $loaders,
        public readonly ?Collection $authors,
        public readonly Collection $projectTypes,
        public readonly Collection $categories,
        public readonly string $platform,
        public bool $default = false,
        public ?int $mergedProjectsCount = null,
        // only when listing dependencies/dependants
        public ?ProjectDependencyType $dependencyType = null,
        public ?Collection $dependencyVersions = null,
        // local mods only
        protected ?Collection $archiveRules = null,
        public ?int $localVersionCount = null
    )
    {
    }

    public static function fromLocal(MasterProject $mp, Project $project, ?Collection $gameVersions = null, ?Collection $loaderDTOs = null): ProjectDTO
    {
        return new self(
            $mp->id,
            $project->remote_id,
            $project->id,
            $mp->name,
            $project->summary,
            $project->description,
            $project->logo,
            [],
            $project->project_url,
            $project->downloads,
            $gameVersions?->toArray(),
            $loaderDTOs,
            $project->authors->map(fn(Author $author) => AuthorDTO::fromLocal($author)),
            $project->project_types->map(fn(ProjectType $c) => $c->type),
            $project->categories->map(fn(Category $c) => CategoryDTO::fromLocal($c)),
            $project->platform,
            $mp->preferred_project_id === $project->getKey(),
            $mp->projects_count,
            null,
            null,
            $project->relationLoaded('archive_rules') ? $project->archive_rules : null,
            $mp->versions_count ?? $project->versions_count,
        );
    }

    public function setArchiveRules(Collection $rules)
    {
        $this->archiveRules = $rules;
    }

    public function setDependencyTypeFromString(string $dependencyType)
    {
        $this->dependencyType = ProjectDependencyType::fromName($dependencyType);
    }
    public function setDependencyType(ProjectDependencyType $dependencyType)
    {
        $this->dependencyType = $dependencyType;
    }

    public function setDependencyVersions(Collection $versions)
    {
        $this->dependencyVersions = $versions;
    }

    public static function fromArray(array $project): ProjectDTO
    {
        return new self(
            $project['id'],
            $project['remote_id'],
            $project['project_id'],
            $project['name'],
            $project['summary'],
            $project['description'],
            $project['logo'],
            $project['gallery'],
            $project['project_url'],
            $project['downloads'],
            $project['game_versions'],
            $project['loaders']
                ? collect(array_map(fn(array $loader) => LoaderDTO::fromArray($loader), $project['loaders']))
                : null,
            $project['authors']
                ? collect(array_map(fn(array $author) => AuthorDTO::fromArray($author), $project['authors']))
                : null,
            collect(array_map(fn($type) => EProjectType::from($type), $project['project_types'])),
            collect(array_map(fn(array $category) => CategoryDTO::fromArray($category), $project['categories'])),
            $project['platform_id'],
        );
    }

    public function toArray(): array
    {
        $project = [
            'id' => $this->id,
            'remote_id' => $this->remoteId,
            'project_id' => $this->projectId,
            'name' => $this->name,
            'summary' => $this->summary,
            'description' => $this->description,
            'logo' => $this->logo,
            'gallery' => $this->gallery,
            'project_url' => $this->projectUrl,
            'downloads' => $this->downloads,
            'game_versions' => $this->gameVersions,
            'loaders' => $this->loaders?->map(fn(LoaderDTO $loader) => $loader->toArray()),
            'authors' => $this->authors,
            'project_types' => $this->projectTypes,
            'categories' => $this->categories,
            'platform' => $this->platform,
            'default' => $this->default,
            'merged_projects_count' => $this->mergedProjectsCount,
            'archive_rules' => $this->archiveRules ? ArchiveRuleResource::collection($this->archiveRules) : [],
            'local_version_count' => $this->localVersionCount
        ];

        if ($this->dependencyType)
            $project['dependency_type'] = $this->dependencyType->name();
        if ($this->dependencyVersions)
            $project['dependency_versions'] = $this->dependencyVersions->toArray();

        return $project;
    }
}
