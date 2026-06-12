<?php

namespace App\API\Loader;

use App\API\DTO\FileDTO;
use App\API\DTO\LibraryDTO;
use App\API\DTO\LoaderInstallProfileDTO;
use App\API\DTO\LoaderVersionDTO;
use App\API\Loader\Base\BaseLoader;
use App\API\McaHttp;
use App\Enums\VersionType;
use App\Services\SettingsService;
use App\Support\HashList;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class LiteLoader extends BaseLoader
{
    protected const LEGACY_INDEX_URL = 'http://dl.liteloader.com/redist/legacy/';
    protected const LEGACY_INDEX_GAME_VERSIONS = [
        '1.3.2', '1.4.0', '1.4.2', '1.4.4', '1.4.5', '1.4.6', '1.4.7', '1.5.0', '1.5.1'
    ];

    protected const INDEX_URL = 'https://dl.liteloader.com/versions/versions.json';
    protected const INDEX_GAME_VERSIONS = ['1.5.2', '1.6.2', '1.6.4', '1.7.2', '1.7.10'];

    public function __construct(protected McaHttp $http, protected SettingsService $settings)
    {
        parent::__construct($http, $this->settings);

        // Project is discontinued, set high cache TTL
        $this->http->setCacheTtl(60 * 60 * 24 * 7);
    }

    public static function name(): string
    {
        return 'LiteLoader';
    }

    public static function registerSettings(SettingsService $settings)
    {
        $settings->registerAutoArchiveSettings('loaders.liteloader');

        $settings->registerArchivingComponentsSettings('loaders.liteloader', ['universal'], [
            'universal', 'sources', 'javadoc', 'staging', 'release',
            ['id' => 'mcpnames', 'name' => 'MCP names'],
            ['id' => 'mcpnames-sources', 'name' => 'MCP names sources'],
            ['id' => 'srgnames-sources', 'name' => 'SRG names sources'],
        ]);

        $settings->registerAutoArchiveFilterSetting('loaders.liteloader', 'latest', [
            ['id' => '*', 'name' => 'All'], 'latest'
        ]);

        $settings->registerAutoArchiveRemoveOldSetting('loaders.liteloader');
    }

    public function isVersionedByGameVersions(): bool
    {
        return true;
    }

    public function getVersion(string $version, array $options = []): Collection
    {
        [$gameVersion, $build] = $this->extractGameVersionAndBuild($version);

        if (in_array($gameVersion, self::LEGACY_INDEX_GAME_VERSIONS)) {
            $versionMetadata = $this->getLegacyVersionsMetadata()
                ->first(fn(array $v) => str_contains($v['name'], $version));

            return collect([
                $this->makeFileDTO(
                    $versionMetadata['name'],
                    'universal',
                    $versionMetadata['url'],
                    $versionMetadata['size'],
                )
            ]);
        }

        if (in_array($gameVersion, self::INDEX_GAME_VERSIONS)) {
            $metadata = $this->fetchVersionManifestFromIndex($gameVersion, $version);

            $files = array_filter([$metadata['file'], data_get($metadata, 'mcpJar'), data_get($metadata, 'srcJar')]);

            $result = [];
            foreach ($files as $fileName) {
                $component = $this->extractComponentName($fileName, $metadata['version']);

                $result[] = $this->makeFileDTO(
                    $fileName,
                    $component,
                    "http://dl.liteloader.com/versions/com/mumfrey/liteloader/$gameVersion/$fileName",
                    hashes: $component === 'universal' ? ['md5' => $metadata['md5']] : []
                );
            }

            return collect($result);
        }

        $jobUrl = "https://jenkins.liteloader.com/job/LiteLoader%20$gameVersion/$build";
        $response = $this->http->get("$jobUrl/api/json?tree=artifacts[fileName,relativePath]");

        $files = [];
        foreach ($response->json('artifacts') as $artifact) {
            // Skip LiteLoader core
            if (str_starts_with($artifact['fileName'], 'liteloader-core'))
                continue;

            $component = $this->extractComponentName($artifact['fileName'], 'SNAPSHOT');

            $files[] = $this->makeFileDTO(
                $artifact['fileName'],
                $component,
                $jobUrl.'/artifact/'.$artifact['relativePath'],
            );
        }

        // Universal is always at the end, so reverse the order
        return collect($files)->reverse()->values();
    }

    public function getVersions(array $options = []): Collection
    {
        $response = $this->http->get('https://jenkins.liteloader.com/api/json?tree=jobs[name,url]');

        $liteLoaderJobs = [];
        foreach ($response->json('jobs') as $job) {
            if (Str::startsWith($job['name'], 'LiteLoader ')) {
                $liteLoaderJobs[] = $job;
            }
        }

        $versions = [];
        foreach ($liteLoaderJobs as $job) {
            $response = $this->http->get($job['url'].'api/json?tree=displayName,allBuilds[number,timestamp,url]');
            $gameVersion = Str::after($response->json('displayName'), 'LiteLoader ');

            foreach ($response->json('allBuilds') as $build) {
                $buildName = $gameVersion.'_'.$build['number'];
                $versions[] = new LoaderVersionDTO(
                    $buildName,
                    $buildName,
                    $this->fixGameVersion($gameVersion),
                    VersionType::SNAPSHOT,
                    Carbon::createFromTimestampMs($build['timestamp'])
                );
            }
        }

        return collect([
            ...$this->getLegacyVersions(),
            ...$this->getOldVersions(),
            ...$versions
        ]);
    }

    protected function getOldVersions(): array
    {
        $response = $this->http->get(self::INDEX_URL);

        $result = [];
        foreach ($response->json('versions') as $gameVersion => $version) {
            if (! in_array($gameVersion, self::INDEX_GAME_VERSIONS))
                continue;

            $artifacts = $this->getArtifactsFromVersionManifest($version);

            foreach ($artifacts as $artifact) {
                $result[] = new LoaderVersionDTO(
                    $artifact['version'],
                    $artifact['version'],
                    $this->fixGameVersion($gameVersion),
                    $artifact['stream'] === 'RELEASE'
                        ? VersionType::RELEASE
                        : VersionType::SNAPSHOT,
                    Carbon::createFromTimestamp($artifact['timestamp'])
                );
            }
        }

        return $result;
    }

    protected function getLegacyVersions(): array
    {
        $result = [];
        foreach ($this->getLegacyVersionsMetadata() as $version) {
            $extension = Str::afterLast($version['name'], '.');
            $fileName = Str::replaceEnd('.'.$extension, '', $version['name']);

            [, $gameVersion, $build] = explode('_', $fileName);
            $versionName = $gameVersion.'_'.$build;
            $result[] = new LoaderVersionDTO(
                $versionName,
                $versionName,
                $this->fixGameVersion($gameVersion),
                VersionType::RELEASE,
                Carbon::make($version['date'].' '.$version['time'])
            );
        }

        return $result;
    }

    private function getLegacyVersionsMetadata(): Collection
    {
        $response = $this->http->getText(self::LEGACY_INDEX_URL);
        $crawler = new Crawler($response);
        $versions = $crawler->filter('pre')->first()->extract(['_text']);

        return collect(explode("\n", $versions[0]))
            ->filter()
            ->map(fn(string $v) => trim($v))
            ->map(function (string $v) {
                // 9/8/2012  6:19 PM  25698  liteloader_1.3.2_00.zip
                [$date, $time, $size, $name] = explode('  ', $v);

                // Special case for misnamed file
                $nameFixed = $name === 'liteloader_1.3.2_00.zip'
                    ? 'liteloader_1.3.2_03.zip'
                    : $name;

                return [
                    ...compact('date', 'time', 'size'),
                    'name' => $nameFixed,
                    'url' => self::LEGACY_INDEX_URL.$name
                ];
            });
    }

    public function getVersionManifest(string $version, Collection $files): LoaderInstallProfileDTO|false
    {
        [$gameVersion, $build] = $this->extractGameVersionAndBuild($version);

        if (in_array($gameVersion, self::LEGACY_INDEX_GAME_VERSIONS)) {
            // Legacy versions do not need any libraries
            $libraries = [];
        } else {
            $metadata = $this->fetchVersionManifestFromIndex($gameVersion, 'latest');
            if ($metadata instanceof Collection) dd($gameVersion, $metadata);
            if ($metadata === null) return false;

            $libraries = array_map(fn(array $library) => new LibraryDTO($library['name']), $metadata['libraries']);
        }

        return new LoaderInstallProfileDTO($version, $gameVersion, null, collect($libraries));
    }

    public function getReleaseDates(Collection $versions): array
    {
        return [];
    }

    // === Other ===

    /**
     * Fetch version manifest and filter it down to specific loader version.
     *
     * @param string $gameVersion
     * @param string $version Specific version or "latest" for latest one
     * @return array|null
     */
    private function fetchVersionManifestFromIndex(string $gameVersion, string $version): ?array
    {
        $artifacts = $this->http->get(self::INDEX_URL)->json(['versions', $gameVersion]);

        if ($artifacts === null) {
            throw new \RuntimeException(sprintf('Failed to fetch LiteLoader version information: %s-%s', $gameVersion, $version));
        }

        return $this->getArtifactsFromVersionManifest($artifacts, $version === 'latest')
            ->first($version !== 'latest'
                ? fn(array $v) => $v['version'] === $version
                : null
            );
    }

    /**
     * Extract game version and build number from version name.
     * E.g. 1.8.0_64 => ['1.8.0', '64']
     *
     * @param string $version
     * @return string[]
     */
    private function extractGameVersionAndBuild(string $version): array
    {
        return explode('_', $version, 2);
    }

    /**
     * Extract the component name from provided file name.
     *
     * @param string $fileName
     * @param string $fileNameEndHint
     * @return string
     */
    private function extractComponentName(string $fileName, string $fileNameEndHint): string
    {
        $component = Str::beforeLast(Str::afterLast($fileName, $fileNameEndHint), '.jar');

        if (str_starts_with($component, '-') && $component !== '-') return substr($component, 1);
        elseif ($component === '') return 'universal';
        else return 'unknown';
    }

    /**
     * Safely extract a list of versions from a manifest.
     *
     * @param array $versionManifest
     * @param bool $latestOnly
     * @return Collection
     */
    private function getArtifactsFromVersionManifest(array $versionManifest, bool $latestOnly = false): Collection
    {
        // NOT a typo
        $artifacts = Arr::get(data_get($versionManifest, 'artefacts'), 'com.mumfrey:liteloader');
        $snapshots = Arr::get(data_get($versionManifest, 'snapshots'), 'com.mumfrey:liteloader');

        if ($artifacts === null && $snapshots === null) {
            Log::stack(['queue', 'stack'])->warning('Failed to extract artifacts from LiteLoader version manifest');
            return collect();
        }

        // Stable releases take priority
        $all = array_merge(...array_filter([$snapshots, $artifacts]));

        $all = $latestOnly
            ? Arr::only($all, 'latest')
            : Arr::except($all, 'latest');

        return collect($all);
    }

    private function makeFileDTO(string $fileName, string $component, string $url, ?int $size = null, array $hashes = []): FileDTO
    {
        return new FileDTO(
            $component,
            $component,
            $component,
            null,
            $fileName,
            $url,
            $size,
            new HashList($hashes),
            $component === 'universal'
        );
    }

    private function fixGameVersion(string $gameVersion): string
    {
        return match ($gameVersion) {
            '1.4.0' => '1.4',
            '1.5.0' => '1.5',
            default => $gameVersion
        };
    }
}
