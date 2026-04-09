<?php

namespace App\API\DTO\Modpack;

use App\API\DTO\DTO;
use App\Support\HashList;

class ModpackModDTO extends DTO
{
    public function __construct(
        public readonly ?string $projectId,
        public readonly ?string $versionId,
        public readonly ?string $fileId,
        public readonly ?string $fileName,
        public readonly bool $clientRequired,
        public readonly bool $serverRequired,
        public readonly ?string $path,
        public readonly ?array $downloads,
        public readonly HashList $hashes,
        public readonly ?int $size,
        // Indicates if we should skip looking for this mod on platform
        public readonly bool $external
    )
    {
    }

    public function hasDownloads(): bool
    {
        return $this->downloads && ! empty($this->downloads);
    }
}
