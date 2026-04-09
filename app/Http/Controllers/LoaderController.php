<?php

namespace App\Http\Controllers;

use App\API\DTO\FileDTO;
use App\API\DTO\VersionDTO;
use App\Http\Controllers\Concerns\ManagesLocalVersions;
use App\Jobs\ArchiveLoaderJob;
use App\Jobs\UpdateLoaderIndexJob;
use App\Models\File;
use App\Models\Loader;
use App\Models\Version;
use App\Resources\JobStatusResource;
use App\Resources\LoaderResource;
use App\Services\JobService;
use App\Services\McaLoaderArchiver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LoaderController extends Controller
{
    use ManagesLocalVersions;

    public function index()
    {
        return LoaderResource::collection(Loader::all());
    }

    public function show($id)
    {
        $loader = Loader::findOrFail($id);

        return new LoaderResource($loader);
    }

    public function updateIndex($id, Request $request, JobService $jobService)
    {
        $loader = Loader::findOrFail($id);
        $status = $jobService->dispatch(
            new UpdateLoaderIndexJob($loader, $request->boolean('revalidate', false)),
            sprintf("%s\n%s", $loader->name, 'Updating Index'),
            sprintf('%s;%s', 'loader-update-index', $loader->id)
        );

        return new JobStatusResource($status);
    }

    public function versions($id, Request $request)
    {
        $loader = Loader::findOrFail($id);

        $versions = Version::query()
            ->with(['files', 'game_versions'])
            ->whereMorphedTo('versionable', $loader)
            ->when($request->boolean('archived_only'), fn(Builder $q) => $q->has('files'))
            ->when($request->has('game_versions'),
                fn(Builder $q) => $q->whereHas('game_versions', fn(Builder $q) =>
                    $q->whereIn('name', (array)$request->get('game_versions'))
                )
            )
            ->when($request->has('release_types'),
                fn(Builder $q) => $q->whereIn('type', (array)$request->get('release_types'))
            )
            ->orderByRaw('published_at IS NULL DESC')
            ->orderByDesc('published_at')
            ->paginate(50);

        return [
            'cached' => false,
            'data' => $versions->map(fn(Version $v) => VersionDTO::fromLocal($v)),
            ...$this->withPaginatorMeta($versions)
        ];
    }

    public function files($id, $versionId, McaLoaderArchiver $archiver)
    {
        $loader = Loader::query()->findOrFail($id);
        $version = Version::query()->forMorph($loader)->with('files')->findOrFail($versionId);

        $files = $archiver->getVersionFiles($loader, $version);

        $files->each(function (FileDTO $file) use ($version) {
            $local = $version->files->first(fn(File $f) => $f->component === $file->component);

            if ($local) $file->setLocal(true);
        });

        return $files;
    }

    public function archive($id, Request $request)
    {
        $version = Version::query()
            ->forMorph(Loader::class)
            ->findOrFail($request->integer('version_id'));

        return $this->doArchive($version, $request->get('components', []));
    }

    public function revalidate($id, $versionId)
    {
        $version = Version::query()
            ->with('files')
            ->forMorph(Loader::class)
            ->findOrFail($versionId);

        if ($version->files->isEmpty()) {
            abort(400);
        }

        return $this->doArchive($version, $version->files->pluck('component')->toArray());
    }

    protected function doArchive(Version $version, array $components)
    {
        $jobService = app(JobService::class);

        $status = $jobService->dispatch(
            new ArchiveLoaderJob($version, $components, true),
            sprintf("%s\n%s", $version->versionable->name, $version->version),
            sprintf('%s;%s', $version->versionable->slug, $version->remote_id)
        );

        return new JobStatusResource($status);
    }

    public function destroy($id, $versionId)
    {
        return $this->doDestroy(Loader::class, $versionId);
    }

    public function destroyFile($id, $versionId, $fileId)
    {
        return $this->doDestroyFile(Loader::class, $versionId, $fileId);
    }
}
