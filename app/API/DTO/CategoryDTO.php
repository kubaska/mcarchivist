<?php

namespace App\API\DTO;

use App\Models\Category;
use App\Models\ProjectType;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class CategoryDTO extends DTO implements Arrayable
{
    /**
     * @param string $id
     * @param string $remoteId
     * @param string $platform
     * @param string $name
     * @param false|string|null $group Only set NULL if category is not assigned to any group.
     *                                 When group exists, but is not available in current request, this should be set to FALSE.
     * @param Collection|null $projectTypes
     * @param string|null $parentId
     * @param Collection|null $children
     */
    public function __construct(
        public readonly string $id,
        public readonly string $remoteId,
        public readonly string $platform,
        public readonly string $name,
        public false|string|null $group,
        public ?Collection $projectTypes,
        public readonly ?string $parentId,
        public ?Collection $children
    )
    {
    }

    public static function fromLocal(Category $category): CategoryDTO
    {
        return new self(
            $category->id,
            $category->remote_id,
            $category->platform,
            $category->name,
            $category->group,
            $category->relationLoaded('project_types')
                ? $category->project_types->map(fn(ProjectType $pt) => $pt->type)
                : null,
            null,
            $category->relationLoaded('children') ? $category->children : null
        );
    }

    public static function fromArray(array $category): CategoryDTO
    {
        return new self(
            $category['id'],
            $category['remote_id'],
            $category['platform'],
            $category['name'],
            null,
            null,
            null,
            null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'remote_id' => $this->remoteId,
            'platform' => $this->platform,
            'name' => $this->name,
            'group' => $this->group,
            'project_types' => $this->projectTypes,
            'children' => $this->children
        ];
    }
}
