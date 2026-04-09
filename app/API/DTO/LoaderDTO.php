<?php

namespace App\API\DTO;

use App\Models\Loader;
use App\Models\LoaderRemote;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class LoaderDTO extends DTO implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $remoteId,
        public readonly ?string $platform,
        public readonly string $name,
        public readonly ?Collection $projectTypes = null
    )
    {
    }

    public static function fromLocal(Loader $loader, ?LoaderRemote $remote = null): LoaderDTO
    {
        return new self(
            $loader->id,
            $remote?->remote_id,
            null,
            $loader->name
        );
    }

    public static function fromArray(array $loader): LoaderDTO
    {
        return new self(
            $loader['id'],
            $loader['remote_id'],
            $loader['platform'],
            $loader['name'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'remote_id' => $this->remoteId,
            'platform' => $this->platform,
            'name' => $this->name,
        ];
    }
}
