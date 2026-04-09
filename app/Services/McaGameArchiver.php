<?php

namespace App\Services;

use App\API\DTO\Game\GameComponentDTO;
use App\API\DTO\Game\GameVersionDTO;
use App\API\Mojang;
use App\Enums\StorageArea;
use App\Jobs\UpdateGameVersionsComponents;
use App\Mca\McaFile;
use App\Models\File;
use App\Models\GameVersion;
use App\Models\Version;
use App\Support\McaFilesystem;
use App\Support\Utils;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Filesystem\Path;

class McaGameArchiver
{
    public function __construct(
        protected Mojang $api,
        protected McaDownloader $downloader,
        protected McaFilesystem $filesystem,
        protected McaLibraryArchiver $libraryArchiver
    )
    {
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

    public function archive(string $version, array $components): Version
    {
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
        Log::stack(['queue', 'stack'])->info("Archiving game version: $version");
        foreach ($assets['objects'] as $path => $asset) {
            $filename = $asset['hash'].'.'.Str::afterLast($path, '.');

            if (! Utils::verifyAssetExists($assetsPath, $filename, $asset['hash'])) {
                Log::stack(['queue', 'stack'])->info("Archiving asset: $path");

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
            if (! Utils::verifyAssetExists($assetsPath, $manifest->loggingFile->name, $manifest->loggingFile->hash)) {
                Log::stack(['queue', 'stack'])->info('Archiving logging configuration: '.$manifest->loggingFile->name);

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

        if ($localFile = $version->files->first(fn(File $f) => $f->component === $component->name)) {
            return $localFile;
        }

        $versionDir = McaFilesystem::makeDirName($gameVersion->name, extendCharset: true);
        $versionPath = $this->filesystem->getStoragePath(StorageArea::GAME, $versionDir, makeDir: true);
        [$alreadyHaveFile, $fileName] = Utils::verifyFileAlreadyExistsAndMakeFileName($versionPath, $component->toFileDTO());

        if ($alreadyHaveFile) {
            $file = new McaFile(Path::join($versionPath, $fileName));
        } else {
            $file = $this->downloader->download(
                $component->url,
                $versionPath,
                $fileName,
                'sha1',
                $component->hash,
                $component->size
            );
        }

        return $version->files()->firstOrCreate(
            ['remote_id' => $component->name],
            [
                'side' => $this->api->getFileSide($component->name),
                'path' => $versionDir, 'file_name' => $fileName, 'original_file_name' => $component->getFileName(),
                'component' => $component->name,
                'hashes' => $file->makeHashList(['sha1' => $component->hash]),
                'size' => $component->size,
                'primary' => Utils::isPrimaryComponent($component->name)
            ]
        );
    }

    private function getSortOrder(string $component): int
    {
        return Utils::$componentOrder[$component] ?? 99;
    }
}
