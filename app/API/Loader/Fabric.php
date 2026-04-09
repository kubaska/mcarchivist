<?php

namespace App\API\Loader;

use App\API\DTO\FileDTO;
use App\API\DTO\LoaderInstallProfileDTO;
use App\API\DTO\LoaderVersionDTO;
use App\API\Loader\Base\BaseLoader;
use App\API\Loader\Base\LoaderCommons;
use App\Models\Version;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class Fabric extends BaseLoader
{
    public static function name(): string
    {
        return 'Fabric';
    }

    public function isVersionedByGameVersions(): bool
    {
        return false;
    }

    /**
     * @param string $version
     * @param array $options
     * @return Collection<FileDTO>
     */
    public function getVersion(string $version, array $options = []): Collection
    {
        $hash = null;

        if (! isset($options['without_hashes'])) {
            $url = "https://maven.fabricmc.net/net/fabricmc/fabric-loader/$version/fabric-loader-$version.jar.sha1";
            $hash = $this->http->getText($url);
        }

        $file = FileDTO::fromFabric(
            "fabric-loader-$version.jar",
            'loader',
            self::getDownloadUrl($version),
            $hash,
            true
        );

        return collect([$file]);
    }

    /**
     * @param array $options
     * @return Collection<LoaderVersionDTO>
     */
    public function getVersions(array $options = []): Collection
    {
        $response = $this->http->get('https://meta.fabricmc.net/v2/versions/loader', array_merge($options, []));

        $versions = [];

        foreach ($response->getData() as $loaderVersion) {
            $versions[] = LoaderVersionDTO::fromFabric($loaderVersion['version'], $loaderVersion['stable']);
        }

        // We want newest-last so reverse the collection.
        return collect($versions)->reverse();
    }

    public function getVersionManifest(string $version, Collection $files): LoaderInstallProfileDTO|false
    {
        $response = $this->http->get(
            "https://maven.fabricmc.net/net/fabricmc/fabric-loader/$version/fabric-loader-$version.json"
        );
        $releaseTime = $response->getHeader('last-modified') ? Carbon::make($response->getHeader('last-modified')) : null;

        return LoaderInstallProfileDTO::fromFabric($version, $releaseTime, $response->getData());
    }

    public function getReleaseDates(Collection $versions): array
    {
        return LoaderCommons::getFabricReleaseDates($versions, fn(Version $v) => self::getDownloadUrl($v->version));
    }

    public static function getDownloadUrl(string $version): string
    {
        return "https://maven.fabricmc.net/net/fabricmc/fabric-loader/$version/fabric-loader-$version.jar";
    }
}
