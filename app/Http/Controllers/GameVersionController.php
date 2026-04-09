<?php

namespace App\Http\Controllers;

use App\API\DTO\Game\GameComponentDTO;
use App\API\DTO\VersionDTO;
use App\Http\Controllers\Concerns\ManagesLocalVersions;
use App\Jobs\ArchiveGameVersion;
use App\Jobs\UpdateGameVersionsIndexJob;
use App\Models\File;
use App\Models\GameVersion;
use App\Models\Version;
use App\Resources\JobStatusResource;
use App\Services\JobService;
use App\Services\McaGameArchiver;
use Illuminate\Http\Request;

class GameVersionController extends Controller
{
    use ManagesLocalVersions;

    public function index(Request $request)
    {
        $gameVersions = Version::query()
            ->forMorph(GameVersion::class)
            ->with(['files'])
            ->when($request->boolean('archived_only'), fn($q) => $q->has('files'))
            ->when($request->has('release_types'),
                fn($q) => $q->whereIn('type', (array)$request->get('release_types', []))
            )
            ->orderByDesc('published_at')
            ->paginate(50);

        return [
            'data' => $gameVersions->map(fn(Version $version) => VersionDTO::fromLocal($version)),
            ...$this->withPaginatorMeta($gameVersions)
        ];
    }

    public function updateIndex(Request $request, JobService $jobService)
    {
        $status = $jobService->dispatch(
            new UpdateGameVersionsIndexJob($request->boolean('revalidate', false)),
            sprintf("%s\n%s", 'Game Versions', 'Updating Index'),
            'game-versions-update-index'
        );

        return new JobStatusResource($status);
    }

    public function files(string $versionId, McaGameArchiver $gameArchiver)
    {
        $version = Version::query()
            ->forMorph(GameVersion::class)
            ->with('files')
            ->findOrFail($versionId);

        $files = $gameArchiver->getVersionFiles($version->version);
        $version->saveAvailableComponentList($files->pluck('name')->toArray());

        return $files->map(function (GameComponentDTO $component) use ($version) {
            $file = $component->toFileDTO();
            $local = $version->files->first(fn(File $f) => $f->component === $component->name);

            if ($local) $file->setLocal(true);
            return $file;
        });
    }

    public function archive(string $versionId, Request $request)
    {
        $version = Version::query()
            ->forMorph(GameVersion::class)
            ->findOrFail($versionId);

        return $this->doArchive($version->version, $request->get('components', []));
    }

    public function revalidate(string $versionId)
    {
        $version = Version::query()
            ->with('files')
            ->forMorph(GameVersion::class)
            ->findOrFail($versionId);

        if ($version->files->isEmpty()) {
            abort(400);
        }

        return $this->doArchive($version->version, $version->files->pluck('component')->toArray());
    }

    protected function doArchive(string $version, array $components)
    {
        $jobService = app(JobService::class);

        $status = $jobService->dispatch(
            new ArchiveGameVersion($version, $components, true),
            sprintf("%s\n%s", 'Minecraft: Java Edition', $version),
            'mc:je;'.$version
        );

        return new JobStatusResource($status);
    }

    public function destroy(string $versionId)
    {
        return $this->doDestroy(GameVersion::class, $versionId);
    }

    public function destroyFile(string $versionId, string $fileId)
    {
        return $this->doDestroyFile(GameVersion::class, $versionId, $fileId);
    }
}
