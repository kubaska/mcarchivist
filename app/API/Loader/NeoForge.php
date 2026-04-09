<?php

namespace App\API\Loader;

use App\API\DTO\FileDTO;
use App\API\DTO\LoaderInstallProfileDTO;
use App\API\DTO\LoaderVersionDTO;
use App\API\Loader\Base\BaseLoader;
use App\API\Loader\Base\LoaderCommons;
use App\Enums\VersionType;
use App\Models\Version;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NeoForge extends BaseLoader
{
    // Legacy index contains 1.20.1 versions only
    protected const INDEX_LEGACY = 'https://maven.neoforged.net/api/maven/versions/releases/net/neoforged/forge';
    protected const INDEX = 'https://maven.neoforged.net/api/maven/versions/releases/net/neoforged/neoforge';
    protected const EXCLUDED_LIBRARIES = [
        '/^net.neoforged:forge:[\d\w\-\.]+:universal$/', // 1.20.1
        '/^net.neoforged:neoforge:[\d\w\-\.]+:universal$/' // 1.20.2+
    ];

    public static function name(): string
    {
        return 'NeoForge';
    }

    public function isVersionedByGameVersions(): bool
    {
        return true;
    }

    /**
     * @param string $version
     * @param array $options
     * @return Collection<FileDTO>
     */
    public function getVersion(string $version, array $options = []): Collection
    {
        $response = $this->http->get($this->getVersionMetaUrl($version));

        $files = data_get($response->getData(), 'files');
        $allFiles = [];

        foreach ($files as $file) {
            if ($file['type'] !== 'FILE') continue;
            if (! Str::endsWith($file['name'], '.jar')) continue;

            $classifier = Str::before(Str::afterLast($file['name'], '-'), '.');

            $hash = null;
            if (! isset($options['without_hashes'])) {
                $hash = $this->http->getText($this->getDownloadUrl($version, $file['name'].'.md5'));
            }

            $allFiles[] = FileDTO::fromForge(
                $file['name'],
                $classifier,
                $this->getDownloadUrl($version, $file['name']),
                $hash,
                in_array($classifier, ['installer', 'universal']),
                (int)$file['contentLength']
            );
        }

        return collect($allFiles);
    }

    /**
     * @param array $options
     * @return Collection<LoaderVersionDTO>
     */
    public function getVersions(array $options = []): Collection
    {
        $indexLegacy = $this->http->get(self::INDEX_LEGACY);
        $index = $this->http->get(self::INDEX);

        $versionsLegacy = data_get($indexLegacy->getData(), 'versions');
        $versions = data_get($index->getData(), 'versions');

        $allLegacyVersions = [];
        $allVersions = [];

        foreach ($versionsLegacy as $version) {
            // Skip versions that exist in manifest but not on server
            if ($version === '1.20.1-47.1.7' || $version === '47.1.82') continue;
            $parts = explode('-', $version);
            $loaderVersion = count($parts) === 1 ? $parts[0] : $parts[1];
            $gameVersion = count($parts) === 1 ? '1.20.1' : $parts[0];

            $allLegacyVersions[] = LoaderVersionDTO::fromForge($loaderVersion, $version, $gameVersion, VersionType::RELEASE);
        }

        foreach ($versions as $version) {
            [$gameVersion, $versionType] = $this->extractGameVersion($version);

            $allVersions[] = LoaderVersionDTO::fromForge($version, $version, $gameVersion, $versionType);
        }

        // Sort by version, since api is returning results in alphabetical order
        $sortVersions = fn(Collection $c) => $c->sort(
            fn(LoaderVersionDTO $a, LoaderVersionDTO $b) => version_compare($a->version, $b->version)
        )->values();

        return $sortVersions(collect($allLegacyVersions))->merge($sortVersions(collect($allVersions)));
    }

    public function getVersionManifest(string $version, Collection $files): LoaderInstallProfileDTO|false
    {
        $json = LoaderCommons::getForgeVersionManifest($files);
        if ($json === false) return false;

        return LoaderInstallProfileDTO::fromNeoforge($json, self::EXCLUDED_LIBRARIES);
    }

    public function getReleaseDates(Collection $versions): array
    {
        $versionsWithDates = $this->getReleaseDatesFromMultiMC($versions);

        // Some versions are not indexed by MultiMC, fetch the rest of them from official source.
        $versions = $versions->filter(fn(Version $v) => ! isset($versionsWithDates[$v->getKey()]));
        // Use "+" to preserve int keys
        return $this->getReleaseDatesFromOfficial($versions) + $versionsWithDates;
    }

    public function getReleaseDatesFromMultiMC(Collection $versions): array
    {
        try {
            $dates = $this->http->get('https://meta.multimc.org/v1/net.neoforged/index.json')->json('versions');
        } catch (\Exception $e) {
            Log::stack(['queue', 'stack'])->error('Failed to fetch NeoForge release dates from MultiMC index!', [$e]);
            return [];
        }

        $dates = Arr::keyBy($dates, 'version');
        $result = [];

        /** @var Version $version */
        foreach ($versions as $version) {
            if (isset($dates[$version->version])) {
                $result[$version->id] = $dates[$version->version]['releaseTime'];
            }
        }

        return $result;
    }

    public function getReleaseDatesFromOfficial(Collection $versions): array
    {
        $chunked = $versions->chunk(10);
        $result = [];

        /** @var Collection $versions */
        foreach ($chunked as $versions) {
            $responses = Http::pool(fn(Pool $pool) => $versions->map(fn(Version $v) => $pool->as($v->id)->get(self::getVersionMetaUrl($v->remote_id))));

            /** @var Version $version */
            foreach ($versions as $version) {
                if ($responses[$version->id]->failed()) {
                    Log::stack(['queue', 'stack'])->error('Failed to fetch NeoForge release date info for version '.$version->version);
                    continue;
                }

                $files = data_get($responses[$version->id], 'files', []);
                if (empty($files)) {
                    Log::stack(['queue', 'stack'])->error('No files for NeoForge version '.$version->version);
                    continue;
                }
                $file = Arr::first(
                    $files,
                    fn(array $file) => str_ends_with($file['name'], '-installer.jar'),
                    Arr::first($files)
                );
                if (! $file) {
                    Log::stack(['queue', 'stack'])->error('Failed to find NeoForge file for version '.$version->version);
                    continue;
                }
                $date = Carbon::createFromTimestamp($file['lastModifiedTime']);
                if (! $date) {
                    Log::stack(['queue', 'stack'])->error('Invalid time format: '.$date);
                    continue;
                }

                $result[$version->id] = $date;
            }
        }

        return $result;
    }

    /**
     * Extract game version from loader name.
     *
     * @param string $version
     * @return array Game Version, Loader Version Type
     */
    public function extractGameVersion(string $version): array
    {
        // "old" versioning
        if (str_starts_with($version, '0.') || str_starts_with($version, '20.') || str_starts_with($version, '21.')) {
            $parts = explode('.', $version);

            // e.g. 21.1.34 becomes 1.21.1 and 20.4.102-beta becomes 1.20.4
            // if version starts with a "0" then it's a snapshot e.g. 0.25w14craftmine.3-beta -> 25w14craftmine
            return [
                str_starts_with($version, '0.')
                    ? $parts[1]
                    : sprintf('1.%s%s', $parts[0], $parts[1] == 0 ? '' : '.'.$parts[1]),
                str_ends_with($version, '-beta') ? VersionType::BETA : VersionType::RELEASE
            ];
        } else {
            // "new" versioning
            // 25.4.0.123, 25.4.0.0-beta -> 25.4
            // 25.3.1.1 -> 25.3.1
            // 25.4.0.0-alpha.1+rc-1 -> 25.4-rc-1
            // 25.4.0.0-alpha.1+rc-1 -> major: 25 | minor: 4 | patch: 0 | incremental: 0 | name: alpha.1 | tag: rc-1
            $didMatch = preg_match(
                '/(?<major>\d+)\.(?<minor>\d+)\.(?<patch>\d+)\.(?<incremental>\d+)(?:-(?<name>[\d\w.]+))?(?:\+(?<tag>[\w-]+))?/',
                $version,
                $matches,
                PREG_UNMATCHED_AS_NULL
            );

            if (! $didMatch) return [null, VersionType::RELEASE];

            return [
                sprintf(
                    '%s.%s%s%s',
                    $matches['major'],
                    $matches['minor'],
                    $matches['patch'] === '0' ? '' : '.'.$matches[2],
                    $matches['tag'] ? '-'.$matches['tag'] : ''
                ),
                $matches['name'] === null
                    ? VersionType::RELEASE
                    : (str_contains($matches['name'], 'beta') ? VersionType::BETA : VersionType::ALPHA)
            ];
        }
    }

    protected function getDownloadUrl(string $version, string $filename): string
    {
        return sprintf(
            'https://maven.neoforged.net/releases/net/neoforged/%s/%s/%s',
            str_starts_with($version, '1.20.1-') ? 'forge' : 'neoforge',
            $version,
            $filename
        );
    }

    protected function getVersionMetaUrl(string $version): string
    {
        return sprintf(
            'https://maven.neoforged.net/api/maven/details/releases/net/neoforged/%s/%s',
            str_starts_with($version, '1.20.1-') ? 'forge' : 'neoforge',
            $version
        );
    }
}
