<?php

namespace App\Services;

use App\API\Contracts\ThirdPartyApi;
use App\API\DTO\FileDTO;
use App\API\DTO\LoaderDTO;
use App\API\DTO\LoaderVersionDTO;
use App\API\Loader\Base\BaseLoader;
use App\Enums\StorageArea;
use App\Enums\VersionType;
use App\Mca\ApiManager;
use App\Mca\McaFile;
use App\Models\File;
use App\Models\GameVersion;
use App\Models\Loader;
use App\Models\LoaderRemote;
use App\Models\ProjectType;
use App\Models\Version;
use App\Support\McaFilesystem;
use App\Support\Utils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Filesystem\Path;

class McaLoaderArchiver
{
    public function __construct(
        protected ApiManager $apiManager,
        protected McaLibraryArchiver $libraryArchiver,
        protected McaFilesystem $filesystem,
        protected McaDownloader $downloader,
        protected SettingsService $settings
    )
    {
    }

    public function importRemoteLoaders()
    {
        $this->apiManager->eachLoader(function (BaseLoader $loader) {
            Loader::firstOrCreate(['name' => $loader::name(), 'slug' => $loader->slug()]);
        });

        $this->apiManager->each(function (ThirdPartyApi $api) {
            $loaders = $api->getLoaders();

            $this->saveRemoteLoaders($api::id(), $loaders);
        });
    }

    public function importLoadersIfMissingRemoteIds(string $platform, Collection $remoteIds)
    {
        $localCount = LoaderRemote::query()
            ->where('platform', $platform)
            ->whereIn('remote_id', $remoteIds)
            ->count();

        if ($remoteIds->count() > $localCount) {
            $api = $this->apiManager->get($platform);
            $remotes = $api->getLoaders();
            $this->saveRemoteLoaders($platform, $remotes);
        }
    }

    /**
     * Save remote mod loaders to database
     *
     * @param string $platform
     * @param Collection<LoaderDTO> $loaders
     * @return Collection<Loader>
     */
    public function saveRemoteLoaders(string $platform, Collection $loaders): Collection
    {
        if ($loaders->isEmpty()) return collect();

        $projectTypes = ProjectType::get();
        $local = Loader::query()
            ->withWhereHas('remotes', fn($q) => $q
                ->where('platform', $platform)
                ->whereIn('remote_id', $loaders->map(fn(LoaderDTO $loader) => $loader->remoteId))
            )->get();

        /** @var LoaderDTO $remoteLoader */
        foreach ($loaders as $remoteLoader) {
            $localLoader = $local->first(fn(Loader $l) => $l->remotes->first()?->remote_id === $remoteLoader->remoteId);

            if (! $localLoader) {
                /** @var Loader $localLoader */
                $localLoader = Loader::firstOrCreate([
                    'name' => $remoteLoader->name, 'slug' => Str::slug($remoteLoader->name)
                ]);
                $remoteRef = $localLoader->remotes()->updateOrCreate(
                    ['platform' => $remoteLoader->platform],
                    ['remote_id' => $remoteLoader->remoteId]
                );

                $localLoader->setRelation('remotes', collect([$remoteRef]));
                $local->push($localLoader);
            }

            if ($remoteLoader->projectTypes) {
                $localLoader->remotes->first()->project_types()->sync(
                    $projectTypes->filter(fn(ProjectType $type) => $remoteLoader->projectTypes->contains($type->type))
                );
            }
        }

        return $local;
    }

    public function updateIndex(Loader $loader, bool $revalidate)
    {
        $api = $this->apiManager->getLoader($loader->name);

        $versions = $api->getVersions()
            ->when($revalidate === false, function (Collection $c) use ($loader) {
                $archived = $loader->versions()->pluck('remote_id')->toArray();

                return $c->filter(fn(LoaderVersionDTO $v) => !in_array($v->fullVersion, $archived));
            });

        /** @var LoaderVersionDTO $version */
        foreach ($versions as $version) {
            $this->archive($api, $loader, $version);
        }
    }

    public function getVersionFiles(Loader $loader, Version $version): Collection
    {
        $api = $this->apiManager->getLoader($loader->name);

        $files = $api->getVersion($version->remote_id, ['without_hashes' => true]);

        $version->saveAvailableComponentList($files->pluck('component')->toArray());

        return $files;
    }

    /**
     * @param BaseLoader $api
     * @param Loader $loader
     * @param LoaderVersionDTO $loaderVersion
     * @return Version
     */
    public function archive(BaseLoader $api, Loader $loader, LoaderVersionDTO $loaderVersion): Version
    {
        $version = $loader->versions()->updateOrCreate(
            ['remote_id' => $loaderVersion->fullVersion],
            ['platform' => $api::id(), 'version' => $loaderVersion->version, 'type' => $loaderVersion->versionType]
        );

        if ($loaderVersion->gameVersion) {
            $gameVersion = GameVersion::firstWhere(['name' => $loaderVersion->gameVersion]);

            if ($gameVersion) {
                $version->game_versions()->sync([$gameVersion->id]);
            } else {
                Log::stack(['queue', 'stack'])->error(
                    'Could not attach game version to loader - invalid game version: '.$loaderVersion->gameVersion
                );
            }
        }

        return $version;
    }

    public function archiveVersion(Version $version, array $components)
    {
        $api = $this->apiManager->getLoader($version->versionable->name);

        Log::stack(['queue', 'stack'])->info(sprintf('Archiving loader: %s %s', $api::name(), $version->remote_id));

        $loaderDirName = McaFilesystem::makeDirName($version->remote_id, extendCharset: true);
        $loaderDir = $this->filesystem->getStoragePath(StorageArea::LOADERS, [$api->slug(), $loaderDirName], makeDir: true);
        $fileDTOs = $api->getVersion($version->remote_id);
        $version->saveAvailableComponentList($fileDTOs->pluck('component')->toArray());
        $fileDTOs = $this->resolveComponentsToArchive($api, $fileDTOs, $components);
        $files = [];

        /** @var FileDTO $fileDTO */
        foreach ($fileDTOs as $fileDTO) {
            // Check if we have this file already
            if ($localFile = $version->files->first(fn(File $f) => $f->remote_id === $fileDTO->remoteId)) {
                $files[] = new McaFile($localFile->getAbsoluteFilePath(StorageArea::LOADERS));
                continue;
            }

            [$alreadyHaveFile, $fileName] = Utils::verifyFileAlreadyExistsAndMakeFileName($loaderDir, $fileDTO);
            $file = ArchiverCommons::downloadFileIfMissing($this->downloader, $fileDTO, $loaderDir, $fileName, $alreadyHaveFile);

            $version->files()->firstOrCreate(
                ['remote_id' => $fileDTO->remoteId],
                [
                    'component' => $fileDTO->component,
                    'original_file_name' => $fileDTO->name,
                    'path' => Path::join($api->slug(), $loaderDirName), 'file_name' => $fileName,
                    'hashes' => $file->makeHashList($fileDTO->hashes->toArray()),
                    'size' => $fileDTO->size ?? $file->getSize(),
                    'primary' => $fileDTO->primary
                ]
            );

            $files[] = $file;
        }

        $manifest = $api->getVersionManifest($version->remote_id, collect($files));
        if ($manifest) {
            if ($manifest->releasedAt) {
                $version->published_at = $manifest->releasedAt;
                $version->save();
            }

            $mirrorList = $api->getMirrorList();
            if (! empty($mirrorList)) {
                Log::stack(['queue', 'stack'])->info('Using mirror list:'. Arr::join($mirrorList, ','));
            }

            Log::stack(['queue', 'stack'])->info('Downloading libraries');
            foreach ($manifest->libraries as $library) {
                Log::stack(['queue', 'stack'])->info('Downloading library: '.$library->name);
                $libraryModels = $this->libraryArchiver->archiveLibrary($library, $mirrorList);
                $version->libraries()->syncWithoutDetaching($libraryModels->pluck('id'));
            }
        }
    }

    protected function resolveComponentsToArchive(BaseLoader $api, Collection $files, array $components): Collection
    {
        if (in_array('*', $components)) return $files;

        if (method_exists($api, 'resolveComponentsToArchive')) {
            return $api->resolveComponentsToArchive($files, $components);
        }

        return $files->filter(fn(FileDTO $file) => in_array($file->component, $components));
    }

    /**
     * Fetch all loader versions that should be automatically archived based on user settings.
     *
     * @param Builder $query
     * @param string $type whereIn or whereNotIn
     * @param Loader $loader
     * @param BaseLoader $api
     */
    public function applyLoaderAutoArchivableVersionsQuery(Builder $query, string $type, Loader $loader, BaseLoader $api)
    {
        $settingPrefix = $api->getSettingPrefix();
        $whereIn = $type === 'whereIn' ? 'whereIn' : 'whereNotIn';

        // use subquery in subquery - workaround MariaDB error 42000
        // (This version of MariaDB doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery)
        $query->$whereIn('id', DB::query()->select('id')->from(
            $loader->versions()
                ->when($this->settings->has($settingPrefix.'.automatic_archive.filter'), function (Builder $q) use ($loader, $settingPrefix, $api) {
                    return match ($this->settings->get($settingPrefix.'.automatic_archive.filter')) {
                        'highlighted' => $q->where('type', VersionType::RELEASE_HIGHLIGHTED),
                        'latest', => $q->when($api->isVersionedByGameVersions(),
                            fn(Builder $q) => $this->applyLatestVersionedFilter($q, $loader),
                            fn(Builder $q) => $q->orderByDesc('published_at')->limit(1)
                        ),
                        default => $q
                    };
                })
                ->when($this->settings->has($settingPrefix.'.automatic_archive.release_types'), function (Builder $q) use ($settingPrefix) {
                    $versionTypes = array_map(
                        fn($type) => VersionType::fromName($type),
                        (array)$this->settings->get($settingPrefix.'.automatic_archive.release_types')
                    );

                    return $q->whereHas('game_versions', fn(Builder $q) => $q->whereIn('type', $versionTypes));
                })
                ->select('versions.id'),
            'archivable_versions_sq'
        ));
    }

    /**
     * Find the latest loader versions, for every game version, sorted by `published_at` column.
     * It is very important that there are no duplicate `published_at` dates.
     *
     * @param Builder $query
     * @param Loader $loader
     * @return Builder
     */
    private function applyLatestVersionedFilter(Builder $query, Loader $loader): Builder
    {
        $latestVersionsById = DB::table('versions')
            ->select('gvv.game_version_id', DB::raw('MAX(versions.published_at) as published_at'))
            ->join('game_version_version AS gvv', 'versions.id', '=', 'gvv.version_id')
            ->where('versions.versionable_type', Model::getActualClassNameForMorph(Loader::class))
            ->where('versions.versionable_id', $loader->getKey())
            ->groupBy('gvv.game_version_id');

        return $query->join('game_version_version AS gvv', 'versions.id', '=', 'gvv.version_id')
            ->join('game_versions', 'gvv.game_version_id', '=', 'game_versions.id')
            ->joinSub($latestVersionsById, 'sq', function (\Illuminate\Database\Query\JoinClause $join) {
                $join->on('game_versions.id', '=', 'sq.game_version_id')
                    ->on('versions.published_at', '=', 'sq.published_at');
            });
    }
}
