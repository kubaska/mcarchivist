<?php

namespace App\API\Loader;

use App\API\DTO\FileDTO;
use App\API\DTO\LoaderInstallProfileDTO;
use App\API\DTO\LoaderVersionDTO;
use App\API\Loader\Base\BaseLoader;
use App\API\Loader\Base\LoaderCommons;
use App\Enums\VersionType;
use App\Exceptions\McaHttpException;
use App\Models\Version;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class Forge extends BaseLoader
{
    protected const PROMOTIONS_URL = 'https://files.minecraftforge.net/net/minecraftforge/forge/promotions_slim.json';
    protected const VERSIONS_URL = 'https://files.minecraftforge.net/net/minecraftforge/forge/maven-metadata.json';
    protected const MIRRORLIST_URL = 'https://files.minecraftforge.net/mirrors-2.0.json';
    protected const EXCLUDED_LIBRARIES = [
        '/net.minecraftforge:minecraftforge:[\d\.]+/',
        '/net.minecraftforge:forge:[\d\.\-]+?:universal/',
        '/net.minecraftforge:forge:[\d\.\-]+-mc172/', // 1.7.2-10.12.2.1161-mc172
        '/net.minecraftforge:forge:[\d\.\-]+/', // 1.7.10 and above
    ];

    public static function name(): string
    {
        return 'Forge';
    }

    public function isVersionedByGameVersions(): bool
    {
        return true;
    }

    /**
     * Fetch list of available mirrors hosting loader files.
     *
     * @return array
     */
    public function getMirrorList(): array
    {
        try {
            $mirrors = $this->http->get(self::MIRRORLIST_URL);
        } catch (\Exception $e) {
            Log::stack(['queue', 'stack'])->error('Unable to download Forge mirror list', [$e]);
            return [];
        }

        $mirrorList = array_unique(array_map(fn($mirror) => $mirror['url'], array_reverse($mirrors->getData())));

        // Remove official host
        return array_filter($mirrorList, fn(string $mirror) => $mirror !== 'https://maven.minecraftforge.net/');
    }

    /**
     * @param string $version
     * @param array $options
     * @return Collection<FileDTO>
     */
    public function getVersion(string $version, array $options = []): Collection
    {
        $response = $this->http->get($this->getVersionMetaUrl($version), array_merge($options, []));

        $data = Arr::first($response->getData());
        $allFiles = [];

        foreach ($data as $classifier => $files) {
            foreach ($files as $extension => $hash) {
                $filename = $this->getFileName($version, $classifier, $extension);

                $allFiles[] = FileDTO::fromForge(
                    $filename,
                    $classifier,
                    $this->getDownloadUrl($version, $filename),
                    $hash,
                    in_array($classifier, ['client', 'server', 'installer', 'universal'])
                );
            }
        }

        return collect($allFiles);
    }

    /**
     * @param array $options
     * @return Collection<LoaderVersionDTO>
     */
    public function getVersions(array $options = []): Collection
    {
        $response = $this->http->get(self::VERSIONS_URL, array_merge($options, []));
        $promotions = $this->http->get(self::PROMOTIONS_URL);
        $promotedVersions = array_values(array_unique(data_get($promotions->getData(), 'promos', [])));

        $versions = [];
        foreach ($response->getData() as $gameVersion => $loaderVersions) {
            // Match official game version name
            $gameVersion = $this->fixForgeGameVersion($gameVersion);

            foreach ($loaderVersions as $loaderVersion) {
                preg_match('/^(?<game>[0-9a-zA-Z_.]+)-(?<version>[0-9.]+)(-(?<branch>[a-zA-Z0-9.]+))?$/', $loaderVersion, $matches);

                if (! $matches['version']) {
                    throw new \RuntimeException('Failed to match Forge version: '.$loaderVersion);
                }

                $versions[] = LoaderVersionDTO::fromForge(
                    $matches['version'],
                    $loaderVersion,
                    $gameVersion,
                    in_array($matches['version'], $promotedVersions)
                        ? VersionType::RELEASE_HIGHLIGHTED
                        : VersionType::RELEASE
                );
            }
        }

        return collect($versions);
    }

    public function getVersionManifest(string $version, Collection $files): LoaderInstallProfileDTO|false
    {
        $json = LoaderCommons::getForgeVersionManifest($files);
        if ($json === false) return false;

        return LoaderInstallProfileDTO::fromForge($json, self::EXCLUDED_LIBRARIES);
    }

    public function getReleaseDates(Collection $versions): array
    {
        $grouped = $versions->groupBy(fn(Version $v) => $v->game_versions->first()?->name);
        $result = [];

        foreach ($grouped as $gameVersion => $versions) {
            if (! $gameVersion) continue;

            Log::stack(['queue', 'stack'])->info('Fetching Forge download index for game version '.$gameVersion);
            $gameVersion = $this->unfixForgeGameVersion($gameVersion);
            try {
                $index = $this->http->getText("https://files.minecraftforge.net/net/minecraftforge/forge/index_{$gameVersion}.html");
            } catch (McaHttpException $e) {
                Log::stack(['queue', 'stack'])->error('Failed to query Forge release date info for version '.$gameVersion);
                continue;
            }

            $html = new Crawler($index);
            $table = $html->filter('table.download-list > tbody')->first();
            if ($table->count() !== 1) {
                Log::stack(['queue', 'stack'])->error('Failed to filter Forge download list');
                continue;
            }

            /** @var Version $version */
            foreach ($versions as $version) {
                $versionElement = $table->children()->reduce(fn(Crawler $node) => $node->filter('.download-version')->innerText() === $version->version);
                if ($versionElement->count() !== 1) {
                    Log::stack(['queue', 'stack'])->error(sprintf('Found %s elements matching Forge version %s.', $versionElement->count(), $version->version));
                    continue;
                }
                $time = Carbon::make($versionElement->first()->filter('.download-time')->first()->attr('title'));
                if (! $time) {
                    Log::stack(['queue', 'stack'])->error('Invalid time format: '.$time);
                    continue;
                }
                $result[$version->id] = $time;
            }
        }

        return $result;
    }

    protected function getFileName(string $version, string $classifier, string $extension): string
    {
        return sprintf('forge-%s-%s.%s', $version, $classifier, $extension);
    }

    protected function getDownloadUrl(string $version, string $filename): string
    {
        return sprintf(
            'https://files.minecraftforge.net/maven/net/minecraftforge/forge/%s/%s',
            $version,
            $filename
        );
    }

    protected function getVersionMetaUrl(string $version): string
    {
        return sprintf('https://files.minecraftforge.net/net/minecraftforge/forge/%s/meta.json', $version);
    }

    /**
     * Resolve components that need to be archived from a list of component names provided by user.
     *
     * @param Collection<FileDTO> $files
     * @param array $components
     * @return Collection
     */
    public function resolveComponentsToArchive(Collection $files, array $components): Collection
    {
        if (in_array('sources', $components)) {
            $components[] = 'src';
        }

        return $files->filter(fn(FileDTO $file) => in_array($file->component, $components));
    }

    /**
     * Match Forge provided game versions with official ones.
     *
     * @param string $gameVersion
     * @return string
     */
    protected function fixForgeGameVersion(string $gameVersion): string
    {
        return match ($gameVersion) {
            '1.4.0' => '1.4',
            '1.7.10_pre4' => '1.7.10-pre4',
            default => $gameVersion
        };
    }

    /**
     * Reverse official game versions to Forge ones.
     *
     * @param string $gameVersion
     * @return string
     */
    protected function unfixForgeGameVersion(string $gameVersion): string
    {
        return match ($gameVersion) {
            '1.4' => '1.4.0',
            '1.7.10-pre4' => '1.7.10_pre4',
            default => $gameVersion
        };
    }
}
