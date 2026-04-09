<?php

namespace App\API\DTO;

use Illuminate\Contracts\Support\Arrayable;

class DependencyDTO extends DTO implements Arrayable
{
    public function __construct(
        public readonly ?string $projectId,
        public readonly ?string $versionId,
        public readonly ?string $filename,
        public readonly string $type
    )
    {
    }

//    public static function fromLocal(Version $dependency): DependencyDTO
//    {
//        return new self(
//            $dependency->versionable_id,
//            $dependency->id,
//            $dependency->version,
//            $dependency->pivot->type
//        );
//    }

    public static function fromArray(array $dependency): DependencyDTO
    {
        return new self(
            $dependency['project_id'],
            $dependency['version_id'],
            $dependency['file_name'],
            $dependency['type'],
        );
    }

    public function toArray(): array
    {
        return [
            'project_id' => $this->projectId,
            'version_id' => $this->versionId,
            'file_name' => $this->filename,
            'type' => $this->type
        ];
    }
}
