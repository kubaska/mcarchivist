<?php

namespace App\API\DTO;

use App\Enums\VersionType;
use Illuminate\Contracts\Support\Arrayable;

class LoaderVersionDTO extends DTO implements Arrayable
{
    public function __construct(
        public readonly string $version,
        public readonly string $fullVersion,
        public readonly ?string $gameVersion,
        public readonly VersionType $versionType
    )
    {
    }

    public static function fromForge(string $version, string $fullVersion, string $gameVersion, VersionType $versionType): LoaderVersionDTO
    {
        return new self($version, $fullVersion, $gameVersion, $versionType);
    }

    public static function fromFabric(string $version, bool $stable, ?string $gameVersion = null): LoaderVersionDTO
    {
        return new self($version, $version, $gameVersion, $stable ? VersionType::RELEASE : VersionType::BETA);
    }

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'full_version' => $this->fullVersion,
            'game_version' => $this->gameVersion,
            'version_type' => $this->versionType
        ];
    }
}
