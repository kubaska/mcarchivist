<?php

namespace App\API;

use App\API\DTO\Game\GameManifestDTO;
use App\API\DTO\Game\GameVersionDTO;
use App\Enums\FileSide;
use Illuminate\Support\Collection;

class Mojang
{
    protected Collection $versions;
    protected array $versionManifests = [];

    public function __construct(protected McaHttp $http)
    {
    }

    /**
     * @return Collection<GameVersionDTO>
     * @throws \App\Exceptions\McaHttpException
     * @throws \App\Exceptions\NotFoundApiException
     */
    public function getVersions(): Collection
    {
        if (isset($this->versions)) return $this->versions;

        $response = $this->http->get('https://piston-meta.mojang.com/mc/game/version_manifest_v2.json')->getData();

        $this->versions = collect($response['versions'])
            ->map(fn($v) => GameVersionDTO::fromMojang($v))
            ->keyBy('name');

        return $this->versions;
    }

    public function getVersion(string $version): GameManifestDTO
    {
        if (isset($this->versionManifests[$version])) {
            return $this->versionManifests[$version];
        }

        /** @var GameVersionDTO $manifest */
        if ($manifest = $this->getVersions()->get($version)) {
            $response = $this->http->get($manifest->url)->getData();
            $dto = GameManifestDTO::fromMojang($response);

            $this->versionManifests[$version] = $dto;

            return $dto;
        }

        throw new \RuntimeException('No such Minecraft version: '.$version);
    }

    public function getAssets(string $version): array
    {
        $manifest = $this->getVersion($version);

        return $this->http->get($manifest->assets['url'])->getData();
    }

    public function resolveAssetUrl(string $assetHash): string
    {
        return sprintf('https://resources.download.minecraft.net/%s/%s', substr($assetHash, 0, 2), $assetHash);
    }

    public function getFileSide(string $gameComponentName): FileSide
    {
        if (str_contains($gameComponentName, 'mappings')) return FileSide::DEVELOPER;

        return match ($gameComponentName) {
            'client' => FileSide::CLIENT,
            'server', 'windows_server' => FileSide::SERVER,
            default => throw new \RuntimeException('No side defined for component: '.$gameComponentName)
        };
    }
}
