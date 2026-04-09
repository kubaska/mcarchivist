<?php

namespace App\Jobs;

use App\API\Contracts\ThirdPartyApi;
use App\API\DTO\Modpack\ModpackModDTO;
use App\API\DTO\VersionDTO;
use App\Enums\JobType;
use App\Enums\ProjectDependencyType;
use App\Enums\StorageArea;
use App\Exceptions\RemoteFilesMissingException;
use App\Exceptions\UnsupportedApiMethodException;
use App\Mca\ApiManager;
use App\Models\File;
use App\Models\Project;
use App\Models\Version;
use App\Services\McaArchiver;
use App\Services\McaDownloader;
use App\Support\McaFilesystem;
use App\Support\Utils;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ArchiveModpack extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected Project $project,
        protected Version $version,
        protected bool $requestedByUser,
        protected bool $revalidate = false
    )
    {
        $this->project = $project->withoutRelations();
        $this->version = $version->withoutRelations();
    }

    public function middleware(): array
    {
        return [
            ...parent::middleware(),
            new WithoutOverlapping($this->project->platform, 10, $this->timeout)
        ];
    }

    public static function getJobType(): JobType
    {
        return JobType::ARCHIVING;
    }

    /**
     * Execute the job.
     *
     * @return int
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle(ApiManager $apiManager, McaArchiver $archiver, McaDownloader $downloader)
    {
        Log::stack(['queue', 'stack'])->info('Archiving modpack...');
        $api = $apiManager->get($this->project->platform);
        $maybeModpackFiles = $this->version->files->filter(fn(File $file) => $api->isModpackFile($file->original_file_name));
        $atLeastOneModpackFileFound = false;

        foreach ($maybeModpackFiles as $modpackFile) {
            $modpackFilePath = $modpackFile->getAbsoluteFilePath(StorageArea::PROJECTS);
            Log::stack(['queue', 'stack'])->info('Considering modpack file: '.$modpackFilePath);

            $installProfile = $api->parseModpackInstallProfile($modpackFilePath);
            if (! $installProfile) continue;
            $atLeastOneModpackFileFound = true;

            Log::stack(['queue', 'stack'])->info(sprintf('Found modpack file: %s', $modpackFile->original_file_name));

            list($files, $mods) = $installProfile->mods->partition(fn(ModpackModDTO $mod) => $mod->external);

            $versions = $this->getVersions($api, $mods);

            if ($versions->count() !== $mods->count()) {
                throw new \RuntimeException(sprintf(
                    'Insufficient mod count returned from API. (need: %s, got: %s)',
                    $mods->count(),
                    $versions->count()
                ));
            }

            foreach ($versions as $version) {
                /** @var ModpackModDTO $modpackMod */
                $modpackMod = $version['mod'];
                /** @var VersionDTO $remoteVersion */
                $remoteVersion = $version['version'];

                $projectId = $modpackMod->projectId ?? $remoteVersion->remoteProjectId;
                if (! $projectId) throw new \RuntimeException('Missing project ID', ['version' => $version]);

                $project = $archiver->archiveProject($this->project->platform, $projectId);
                $localVersion = null;

                if ($modpackMod->fileId) {
                    $localVersion = $archiver->archiveProjectFileDto(
                        $project, $remoteVersion, [$modpackMod->fileId], revalidate: $this->revalidate
                    );
                    $localVersion->markFilesCreatedByUser([$modpackMod->fileId]);
                }
                elseif ($modpackMod->hashes->isNotEmpty()) {
                    foreach ($modpackMod->hashes->toArray() as $algo => $hash) {
                        if ($localVersion) continue;

                        try {
                            $localVersion = $archiver->archiveProjectFilesByHash(
                                $project, $remoteVersion, [$hash], $algo, revalidate: $this->revalidate
                            );

                            $localVersion->markFilesCreatedByUser(
                                $localVersion->files()->where('primary', true)->get()->pluck('remote_id')->toArray()
                            );
                        } catch (RemoteFilesMissingException $e) {
                            // ...
                        }
                    }
                }

                if (! $localVersion) {
                    throw new \RuntimeException('Could not find a way to archive version', ['version' => $version]);
                }


                $this->version->addDependency($project, $localVersion, ProjectDependencyType::REQUIRED);
            }

            /** @var ModpackModDTO $file */
            foreach ($files as $file) {
                [$algo, $hash] = $file->hashes->getFirstHash();

                $downloader->downloadFromMirrorList(
                    $file->downloads,
                    $modpackFile->getAbsoluteDirectoryPath(StorageArea::PROJECTS).DIRECTORY_SEPARATOR.$this->version->id.'_extra',
                    McaFilesystem::makeFileName($file->fileName),
                    $algo,
                    $hash,
                    $file->size
                );
            }
        }

        if (! $atLeastOneModpackFileFound) {
            Log::stack(['queue', 'stack'])->warning(sprintf(
                'No modpack install profile found for %s (%s).', $this->project->name, $this->version->version
            ));
        }

        return 0;
    }

    private function getVersions(ThirdPartyApi $api, Collection $mods): Collection
    {
        if ($mods->every(fn(ModpackModDTO $mod) => $mod->versionId)) {
            $versions = $api->getVersions($mods->map(fn(ModpackModDTO $mod) => $mod->versionId)->toArray())
                ->getData();

            return $mods->map(fn(ModpackModDTO $m) => [
                'mod' => $m,
                'version' => $versions->first(
                    fn(VersionDTO $v) => $v->id === $m->versionId,
                    fn() => throw new \RuntimeException('Missing mod: '.$m->versionId)
                )
            ]);
        }

        $commonHashAlgos = Utils::findCommonHashAlgos($mods->map(fn(ModpackModDTO $m) => $m->hashes->getAlgos())->toArray());

        foreach ($commonHashAlgos as $hashAlgo) {
            if ($versions = $this->getVersionsFromModHashes($api, $mods, $hashAlgo))
                return $versions;
        }

        throw new \RuntimeException('Could not find a way to fetch version info');
    }

    private function getVersionsFromModHashes(ThirdPartyApi $api, Collection $mods, string $algorithm): Collection|false
    {
        if ($mods->every(fn(ModpackModDTO $mod) => $mod->hashes->has($algorithm))) {
            try {
                $versions = $api->getVersionsFromHashes(
                    $mods->map(fn(ModpackModDTO $mod) => $mod->hashes->get($algorithm))->toArray(),
                    $algorithm
                )->getData();

                return $mods->map(fn(ModpackModDTO $m) => [
                    'mod' => $m,
                    'version' => $versions->get(
                        $m->hashes->get($algorithm),
                        fn() => throw new \RuntimeException(sprintf('Missing mod %s hash: %s', $algorithm, $m->hashes->get($algorithm)))
                    )
                ]);
            }
            catch (UnsupportedApiMethodException $e) {}
        }

        return false;
    }
}
