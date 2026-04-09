<?php

namespace App\Services;

use App\API\DTO\DependencyDTO;
use App\API\DTO\FileDTO;
use App\API\DTO\LoaderDTO;
use App\API\DTO\VersionDTO;
use App\API\DTO\ProjectDTO;
use App\Enums\DependencyQualifier;
use App\Enums\StorageArea;
use App\Enums\FileQualifier;
use App\Enums\ProjectDependencyType;
use App\Exceptions\RemoteFilesMissingException;
use App\Jobs\UpdateGameVersionsIndexJob;
use App\Mca\ApiManager;
use App\Models\File;
use App\Models\GameVersion;
use App\Models\Project;
use App\Models\Version;
use App\Support\McaFilesystem;
use App\Support\Utils;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class McaArchiver
{
    public function __construct(
        protected ApiManager $apiManager,
        protected AuthorService $authorService,
        protected CategoryService $categoryService,
        protected McaLoaderArchiver $loaderArchiver,
        protected McaFilesystem $filesystem,
        protected McaDownloader $downloader
    )
    {
    }

    public function archiveProject(string $platform, string $id): Project
    {
        $api = $this->apiManager->get($platform);

        /** @var ProjectDTO $remoteProject */
        $remoteProject = $api->getProject($id)->getData();

        $localProject = Project::saveProject($remoteProject);

        $this->authorService->archiveAuthors($localProject, $remoteProject);
        $this->categoryService->archiveRemoteCategories($localProject, $remoteProject);

        if ($remoteProject->loaders?->isNotEmpty()) {
            $this->loaderArchiver->importLoadersIfMissingRemoteIds(
                $remoteProject->platform,
                $remoteProject->loaders->map(fn(LoaderDTO $loader) => $loader->remoteId),
            );
        }

        return $localProject;
    }

    public function archiveProjectFile(
        Project $project, string $versionId, array $fileIds,
        DependencyQualifier $dependencyQualifier = DependencyQualifier::NONE, bool $revalidate = false
    ): Version
    {
        $api = $this->apiManager->get($project->platform);

        /** @var VersionDTO $remoteVersion */
        $remoteVersion = $api->getVersion($project->remote_id, $versionId)->getData();

        return $this->archiveProjectFileDto(
            $project, $remoteVersion, $fileIds, $dependencyQualifier, $revalidate
        );
    }

    public function archiveProjectFiles(
        Project             $project, string|VersionDTO $remoteVersion, FileQualifier $fileQualifier = FileQualifier::PRIMARY_ONLY,
        DependencyQualifier $dependencyQualifier = DependencyQualifier::NONE
    ): Version
    {
        $api = $this->apiManager->get($project->platform);

        if (!($remoteVersion instanceof VersionDTO)) {
            $remoteVersion = $api->getVersion($project->remote_id, $remoteVersion)->getData();
        }

        if ($fileQualifier === FileQualifier::ALL) {
            $files = $api->getVersionFiles($project->remote_id, $remoteVersion->remoteId);
        }
        elseif($fileQualifier === FileQualifier::PRIMARY_ONLY) {
            $findPrimaryFiles = fn(Collection $files) => $files->filter(fn(FileDTO $file) => $file->primary);

            $files = $findPrimaryFiles($remoteVersion->files);

            if ($files->isEmpty()) {
                $allFiles = $api->getVersionFiles($project->remote_id, $remoteVersion->remoteId);
                $files = $findPrimaryFiles($allFiles->getData());

                if ($files->isEmpty()) {
                    if ($allFiles->getData()->isEmpty()) {
                        throw new \RuntimeException('Unable to find primary files for version '.$remoteVersion->remoteId);
                    }

                    // If there are no primary files, just take the first one
                    $files = $allFiles->getData()->take(1);
                }
            }
        }
        else {
            throw new \LogicException('Unexpected value of FileQualifier: '.$fileQualifier->value);
        }

        return $this->archiveProjectFileDto(
            $project, $remoteVersion,
            $files->map(fn(FileDTO $file) => $file->remoteId)->toArray(),
            $dependencyQualifier
        );
    }

    /**
     * @throws RemoteFilesMissingException
     */
    public function archiveProjectFilesByHash(
        Project $project, VersionDTO $remoteVersion, array $fileHashes, string $algorithm,
        DependencyQualifier $dependencyQualifier = DependencyQualifier::NONE, bool $revalidate = false
    )
    {
        $api = $this->apiManager->get($project->platform);

        $hasAllHashes = function (Collection $files) use ($fileHashes, $algorithm) {
            if (! $files->every(fn(FileDTO $file) => $file->hashes->get($algorithm)))
                return false;

            $files = $files->filter(fn(FileDTO $file) => in_array($file->hashes->get($algorithm), $fileHashes));

            if ($files->count() === count($fileHashes)) return $files;
            else return false;
        };

        $files = $hasAllHashes($remoteVersion->files);
        if (! $files) {
            // Try to get all files if we didn't find hash we were looking for
            $remoteFiles = $api->getVersionFiles($project->remote_id, $remoteVersion->remoteId)->getData();
            $files = $hasAllHashes($remoteFiles);

            if (! $files) throw new RemoteFilesMissingException('Could not find all wanted file hashes');
        }

        return $this->archiveProjectFileDto(
            $project, $remoteVersion, $files->map(fn(FileDTO $file) => $file->id)->toArray(), $dependencyQualifier, $revalidate
        );
    }

    public function archiveProjectFileDto(
        Project             $project, VersionDTO $remoteVersion, array $fileIds,
        DependencyQualifier $dependencyQualifier = DependencyQualifier::NONE, bool $revalidate = false
    ): Version
    {
        $api = $this->apiManager->get($project->platform);

        $dependencies = [];
        if ($dependencyQualifier !== DependencyQualifier::NONE) {
            $dependencyList = $dependencyQualifier === DependencyQualifier::REQUIRED_ONLY
                ? $remoteVersion->dependencies->filter(fn(DependencyDTO $dep) => $dep->type === 'required')
                : $remoteVersion->dependencies;

            /** @var DependencyDTO $dependency */
            foreach ($dependencyList as $dependency) {
                $depProject = $this->archiveProject($project->platform, $dependency->projectId);

                Log::stack(['queue', 'stack'])->info(sprintf(
                    'Archiving dependency ID [%s] | version ID [%s] | game versions [%s]',
                    $dependency->projectId, $dependency->versionId, Arr::join($remoteVersion->getGameVersionNames(), ',')
                ));

                if ($dependency->versionId) {
                    Log::stack(['queue', 'stack'])->info('Archiving exact dependency: '.$dependency->versionId);
                    $depVersion = $this->archiveProjectFiles(
                        $depProject, $dependency->versionId, FileQualifier::PRIMARY_ONLY, $dependencyQualifier
                    );

                    $dependencies[] = ['project' => $depProject, 'version' => $depVersion, 'type' => ProjectDependencyType::fromName($dependency->type)];
                } else {
                    Log::stack(['queue', 'stack'])->info('Fetching newest dependencies for project ID '.$dependency->projectId);
                    $depVersions = $this->getDependencyVersions($api, $depProject, $dependency->projectId, $remoteVersion);

                    /** @var VersionDTO $dv */
                    foreach ($depVersions as $dv) {
                        $depVersion = $this->archiveProjectFiles(
                            $depProject, $dv, FileQualifier::PRIMARY_ONLY, $dependencyQualifier
                        );

                        $dependencies[] = ['project' => $depProject, 'version' => $depVersion, 'type' => ProjectDependencyType::fromName($dependency->type)];
                    }
                }
            }
        }

        // Check if we already have this version and all files
        if (! $revalidate) {
            if ($version = $this->isVersionAlreadyArchived($project, $remoteVersion, $fileIds)) {
                $version->fill(['type' => $remoteVersion->type, 'changelog' => $remoteVersion->changelog])->save();
                return $version;
            }
        }

        // Want all files
        if (in_array('*', $fileIds)) {
            $files = $api->getVersionFiles($project->remote_id, $remoteVersion->remoteId)->getData();
        } else {
            $files = $remoteVersion->files->filter(fn(FileDTO $f) => in_array($f->remoteId, $fileIds));

            if ($files->count() !== count($fileIds)) {
                $files = $api->getVersionFiles($project->remote_id, $remoteVersion->remoteId)->getData();

                $files = $files->filter(fn(FileDTO $f) => in_array($f->remoteId, $fileIds));

                if ($files->count() !== count($fileIds)) {
                    throw new \RuntimeException(sprintf(
                        'Unable to fetch wanted files! Have: %s, Missing: %s',
                        Arr::join($fileIds, ','),
                        Arr::join(array_diff($fileIds,  $files->map(fn(FileDTO $f) => $f->remoteId)->toArray()), ',')
                    ));
                }
            }
        }

        DB::beginTransaction();

        try {
            $version = $this->saveVersion($project, $remoteVersion);

            foreach ($dependencies as $dependency) {
                $version->addDependency($dependency['project'], $dependency['version'], $dependency['type']);
            }

            /** @var FileDTO $remoteFile */
            foreach ($files as $remoteFile) {
                $this->saveFile($project, $version, $remoteFile);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        DB::commit();
        return $version;
    }

    protected function getDependencyVersions($api, Project $project, string $projectId, VersionDTO $version): array
    {
        $dependencies = [];
        $allVersions = collect($api->getProjectVersionsForGameVersions($projectId, $version->getGameVersionNames())->getData());

        Log::stack(['queue', 'stack'])->info('Found dependencies for project ID '.$projectId);
        $this->logVersions($allVersions);

        foreach ($version->getGameVersionNames() as $gameVersion) {
            $depVersions = $allVersions
                ->filter(fn(VersionDTO $mv) => $mv->hasGameVersions([$gameVersion]))
                ->sortBy([['type', 'asc'], ['published_at', 'desc']]);

            if ($version->qualifiesForCurseforgeLoaderWorkaround()) {
                Log::stack(['queue', 'stack'])->info('Using Curseforge loader workaround');

                $depVersions = $depVersions->filter(
                    fn(VersionDTO $mv) => $mv->loaders->isEmpty() || $mv->loaders->contains('Forge')
                );
            } else {
                if ($version->loaders->isEmpty()) {
                    $depVersions = $depVersions->filter(fn(VersionDTO $mv) => $mv->loaders->isEmpty());
                } else {
                    $depVersions = $depVersions->filter(fn(VersionDTO $mv) => $mv->hasAnyLoader($version->loaders));
                }
            }

            $depVersions = $depVersions->sortBy([['type', 'asc'], ['published_at', 'desc']]);

            Log::stack(['queue', 'stack'])->info(sprintf('Sorted dependencies for project ID %s, game version %s', $projectId, $gameVersion));
            $this->logVersions($depVersions);

            if ($depVersions->isEmpty()) {
                Log::stack(['queue', 'stack'])->error(
                    sprintf(
                        'Could not find dependency %s (ID: %s) that satisfies given requirements: game version: %s%s',
                        $project->name,
                        $projectId,
                        $gameVersion,
                        $version->loaders->isNotEmpty() ? ', loaders: '.$version->loaders->pluck('name')->join(',') : ''
                    )
                );
            } else {
                if ($version->loaders->isEmpty()) {
                    $stagedVersions = [$depVersions->first()];
                } else {
                    $stagedVersions = [];
                    /** @var LoaderDTO $loader */
                    foreach ($version->loaders as $loader) {
                        $v = $depVersions->first(fn(VersionDTO $mv) => $mv->hasLoader($loader->name));
                        if ($v) $stagedVersions[$v->id] = $v;
                        else {
                            Log::stack(['queue', 'stack'])->warning(
                                sprintf('Unable to find dependency for loader %s, skipping.', $loader->name)
                            );
                        }
                    }
                }

                foreach ($stagedVersions as $stagedVersion) {
                    if (! isset($dependencies[$stagedVersion->id])) {
                        $dependencies[$stagedVersion->id] = $stagedVersion;
                    }
                }
            }
        }

        return $dependencies;
    }

    protected function isVersionAlreadyArchived(Project $project, VersionDTO $remoteVersion, array $fileIds): Version|false
    {
        $version = Version::query()
            ->whereMorphedTo('versionable', $project)
            ->with('files')
            ->where('platform', $remoteVersion->platform)
            ->where('remote_id', $remoteVersion->id)
            ->first();

        if (! $version) return false;

        foreach ($fileIds as $fileId) {
            if ($version->files->doesntContain(fn(File $file) => $file->remote_id === $fileId))
                return false;
        }

        Log::stack(['queue', 'stack'])->info(sprintf('Skipping archiving version %s: already have all files', $remoteVersion->name));
        return $version;
    }

    private function saveVersion(Project $project, VersionDTO $version): Version
    {
        $model = $project->versions()->updateOrCreate(
            ['platform' => $version->platform, 'remote_id' => $version->id],
            ['version' => $version->name, 'type' => $version->type, 'changelog' => $version->changelog, 'published_at' => $version->publishedAt]
        );

        if ($version->includesGameVersions()) {
            $fetchGameVersions = fn() => GameVersion::query()
                ->whereIn('name', $version->getGameVersionNames())
                ->get();

            $gameVersions = $fetchGameVersions();

            // Try to re-import game versions.
            if ($gameVersions->isEmpty()) {
                try {
                    dispatch_now(new UpdateGameVersionsIndexJob());
                } catch (\Exception $e) {
                    Log::stack(['queue', 'stack'])->error('Failed to refresh game versions', [$e]);
                }

                $gameVersions = $fetchGameVersions();
            }

            if ($gameVersions->isNotEmpty()) {
                $model->game_versions()->sync($gameVersions->pluck('id'));
            }
        }

        $localLoaders = $this->loaderArchiver->saveRemoteLoaders($version->platform, $version->loaders);
        $model->loaders()->sync($localLoaders);

        return $model;
    }

    private function saveFile(Project $project, Version $version, FileDTO $file): File
    {
        if ($localFile = $version->files->first(fn(File $f) => $f->remote_id === $file->remoteId)) {
            return $localFile;
        }

        $projectDirName = $project->master_project->archive_dir;
        $projectDir = $this->filesystem->getStoragePath(StorageArea::PROJECTS, $projectDirName, makeDir: true);

        [$alreadyHaveFile, $fileName] = Utils::verifyFileAlreadyExistsAndMakeFileName($projectDir, $file);
        $fileRef = ArchiverCommons::downloadFileIfMissing($this->downloader, $file, $projectDir, $fileName, $alreadyHaveFile);

        return $version->files()->updateOrCreate(
            ['remote_id' => $file->id],
            [
                'path' => $projectDirName,
                'file_name' => $fileName,
                'original_file_name' => $file->name,
                'hashes' => $fileRef->makeHashList($file->hashes->toArray()),
                'size' => $file->size,
                'primary' => $file->primary
            ]
        );
    }

    private function logVersions(Collection $versions)
    {
        /** @var VersionDTO $v */
        foreach ($versions as $v) {
            Log::stack(['queue', 'stack'])->info(sprintf('  [%s] %s | %s | %s | %s | %s | %s deps',
                $v->type->nameShort(), $v->id, $v->name, $v->loaders->map(fn(LoaderDTO $l) => $l->name)->join(','),
                Arr::join($v->getGameVersionNames(), ','), $v->publishedAt, $v->dependencies->count()
            ));
        }
    }
}
