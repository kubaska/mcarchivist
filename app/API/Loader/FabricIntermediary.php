<?php

namespace App\API\Loader;

use App\API\DTO\FileDTO;
use App\API\DTO\LoaderInstallProfileDTO;
use App\API\DTO\LoaderVersionDTO;
use App\API\Loader\Base\BaseLoader;
use App\API\Loader\Base\LoaderCommons;
use App\Models\Version;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class FabricIntermediary extends BaseLoader
{
    public static function name(): string
    {
        return 'Fabric Intermediary';
    }

    public function isVersionedByGameVersions(): bool
    {
        return true;
    }

    /**
     * @param string $version
     * @param array|null $options
     * @return Collection<FileDTO>
     */
    public function getVersion(string $version, ?array $options = []): Collection
    {
        $hash = null;

        if (! isset($options['without_hashes'])) {
            $url = "https://maven.fabricmc.net/net/fabricmc/intermediary/$version/intermediary-$version.jar.sha1";
            $hash = $this->http->getText($url);
        }

        $file = FileDTO::fromFabric(
            "intermediary-$version.jar",
            'intermediary',
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
        $intermediaries = $this->http->get('https://meta.fabricmc.net/v2/versions/intermediary', array_merge($options, []));

        // Filter out empty 26.1+ versions
        $intermediaries = collect($intermediaries->getData())
            ->skipUntil(fn(array $version) => $version['version'] === '1.21.11');

        return $intermediaries->map(function (array $loaderVersion) {
            $gameVersion = str_ends_with($loaderVersion['version'], '_unobfuscated')
                ? str_replace('_unobfuscated', '', $loaderVersion['version'])
                : $loaderVersion['version'];

            $gameVersion = str_ends_with($loaderVersion['version'], '_original')
                ? str_replace('_original', '', $loaderVersion['version'])
                : $gameVersion;

            return LoaderVersionDTO::fromFabric(
                $loaderVersion['version'],
                $loaderVersion['stable'],
                $gameVersion
            );
        });
    }

    public function getVersionManifest(string $version, Collection $files): LoaderInstallProfileDTO|false
    {
        // No manifest
        return false;
    }

    public function getReleaseDates(Collection $versions): array
    {
        return LoaderCommons::getFabricReleaseDates($versions, fn(Version $v) => self::getDownloadUrl($v->version));
    }

    public static function getDownloadUrl(string $version): string
    {
        return "https://maven.fabricmc.net/net/fabricmc/intermediary/$version/intermediary-$version.jar";
    }
}
