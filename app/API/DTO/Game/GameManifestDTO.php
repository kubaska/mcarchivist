<?php

namespace App\API\DTO\Game;

use App\API\DTO\DTO;
use App\API\DTO\LibraryDTO;
use App\Enums\VersionType;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class GameManifestDTO extends DTO
{
    /**
     * @param string $version
     * @param array $assets
     * @param Collection<GameComponentDTO> $downloads
     * @param Collection<LibraryDTO> $libraries
     * @param GameComponentDTO|null $loggingFile
     * @param Carbon $releaseTime
     * @param VersionType $versionType
     */
    public function __construct(
        public readonly string $version,
        public readonly array $assets,
        public readonly Collection $downloads,
        public readonly Collection $libraries,
        public readonly ?GameComponentDTO $loggingFile,
        public readonly Carbon $releaseTime,
        public readonly VersionType $versionType
    )
    {
    }

    public function getComponents(array $components): Collection
    {
        if (in_array('*', $components)) return $this->downloads;
        return $this->downloads->filter(fn(GameComponentDTO $c) => in_array($c->name, $components));
    }

    public function getComponentNames(): Collection
    {
        return $this->downloads->map(fn(GameComponentDTO $c) => $c->name)->values();
    }

    public static function fromMojang(array $manifest): GameManifestDTO
    {
        return new self(
            $manifest['id'],
            $manifest['assetIndex'],
            collect(Arr::map($manifest['downloads'], fn($component, $name) => GameComponentDTO::fromMojang($name, $component))),
            collect(array_map(fn($lib) => LibraryDTO::fromMojang($lib), $manifest['libraries'])),
            data_get($manifest, 'logging.client.file')
                ? GameComponentDTO::fromMojang($manifest['logging']['client']['file']['id'], $manifest['logging']['client']['file'])
                : null,
            Carbon::make($manifest['releaseTime']),
            VersionType::fromMojang($manifest['type']),
        );
    }
}
