<?php

namespace App\Services;

use App\API\DTO\Game\GameComponentDTO;
use App\API\DTO\Game\GameVersionDTO;
use App\API\Mojang;
use App\Enums\StorageArea;
use App\Models\File;
use App\Models\GameVersion;
use App\Models\Version;
use App\Support\McaFilesystem;
use App\Support\Utils;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Filesystem\Path;

class McaGameArchiver extends BaseArchiver
{
    public function __construct(
        protected Mojang $api,
        protected McaDownloader $downloader,
        protected McaFilesystem $filesystem,
        protected McaLibraryArchiver $libraryArchiver
    )
    {
        parent::__construct($this->downloader, $this->filesystem);
    }

    public function importGameVersions(bool $revalidate)
    {
        $gameVersions = $this->api->getVersions()
            ->when($revalidate === false, function (Collection $c) {
                $saved = GameVersion::query()->get(['id', 'name'])->pluck('name')->toArray();

                return $c->filter(fn(GameVersionDTO $v) => ! in_array($v->name, $saved));
            })
            ->sortBy(fn(GameVersionDTO $v) => $v->getReleaseTime()->timestamp);

        $makeVersion = fn(GameVersion $gv) => $gv->version()->updateOrCreate(
            ['remote_id' => $gv->name],
            ['platform' => 'mojang', 'version' => $gv->name, 'type' => $gv->type, 'published_at' => $gv->released_at]
        );

        /** @var GameVersionDTO $gameVersion */
        foreach ($gameVersions as $gameVersion) {
            $gv = GameVersion::updateOrCreate(
                ['name' => $gameVersion->name, 'official' => true],
                ['type' => $gameVersion->type, 'released_at' => $gameVersion->getReleaseTime()]
            );

            $makeVersion($gv);
        }

        // Make sure a Version exists for every GameVersion
        $missingVersion = GameVersion::query()->whereDoesntHave('version')->get();
        $missingVersion->each(fn(GameVersion $gv) => $makeVersion($gv));
    }

    public function getVersionFiles(string $version): Collection
    {
        $manifest = $this->api->getVersion($version);

        return $manifest->downloads
            ->sort(fn(GameComponentDTO $a, GameComponentDTO $b) => $this->getSortOrder($a->name) <=> $this->getSortOrder($b->name))
            ->values();
    }

    public function archive(string $version, array $components, bool $revalidate = false): Version
    {
        Log::stack(['queue', 'stack'])->info(
            sprintf('Archiving game version %s; selected components: %s', $version, Arr::join($components, ', '))
        );

        $localGameVersion = GameVersion::where('name', $version)->firstOrFail();
        $manifest = $this->api->getVersion($version);
        $assets = $this->api->getAssets($version);

        $localVersion = $localGameVersion->version()->updateOrCreate([], [
            'remote_id' => $localGameVersion->name, 'version' => $localGameVersion->name,
            'components' => $manifest->getComponentNames(), 'platform' => 'mojang',
            'type' => $localGameVersion->type, 'published_at' => $localGameVersion->released_at
        ]);
        $localVersion->load('files');

        // Component can be "client", "server", "windows_server", "client_mappings", "server_mappings", etc.
        foreach ($manifest->getComponents($components) as $component) {
            $this->archiveGameFile($localGameVersion, $localVersion, $component);
        }

        $localLibraries = collect();
        foreach ($manifest->libraries as $library) {
            $localLibraries->push(...$this->libraryArchiver->archiveLibrary($library));
        }

        $localVersion->libraries()->sync($localLibraries->pluck('id'));

        $assetsPath = $this->filesystem->getStoragePath(StorageArea::ASSETS);

        // Check if asset file exists
        // When user wants to revalidate files, additionally do a hash check.
        $assetExists = fn(string $fileName, string $hash) =>
            file_exists($pathToAsset = Path::join($assetsPath, $fileName))
            && ($revalidate === false || hash_file('sha1', $pathToAsset) === $hash);

        foreach ($assets['objects'] as $path => $asset) {
            $filename = $asset['hash'].'.'.Str::afterLast($path, '.');

            if (! $assetExists($filename, $asset['hash'])) {
                Log::info("Archiving asset: $path");

                $this->downloader->download(
                    $this->api->resolveAssetUrl($asset['hash']),
                    $assetsPath,
                    $filename,
                    'sha1',
                    $asset['hash'],
                    $asset['size'],
                    ['force_overwrite' => true]
                );
            }
        }

        if ($manifest->loggingFile) {
            if (! $assetExists($manifest->loggingFile->name, $manifest->loggingFile->hash)) {
                Log::info('Archiving logging configuration: '.$manifest->loggingFile->name);

                $this->downloader->download(
                    $manifest->loggingFile->url,
                    $assetsPath,
                    $manifest->loggingFile->name,
                    'sha1',
                    $manifest->loggingFile->hash,
                    $manifest->loggingFile->size,
                    ['force_overwrite' => true]
                );
            }
        }

        return $localVersion;
    }

    protected function archiveGameFile(GameVersion $gameVersion, Version $version, GameComponentDTO $component): File
    {
        Log::stack(['queue', 'stack'])->info("Archiving $component->name...");

        $versionDir = McaFilesystem::makeDirName($gameVersion->name, extendCharset: true);

        return $this->archiveFile($version, $component->toFileDTO(), $versionDir);
    }

    private function getSortOrder(string $component): int
    {
        return Utils::$componentOrder[$component] ?? 99;
    }
}
