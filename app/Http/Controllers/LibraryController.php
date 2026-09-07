<?php

namespace App\Http\Controllers;

use App\API\DTO\FileDetailsDTO;
use App\Models\GameVersion;
use App\Models\Library;
use App\Models\Version;
use App\Resources\LibraryResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $libraries = Library::query()
            ->when($request->has('query'), fn(Builder $q) =>
                $q->whereLike('name', '%'.$request->input('query').'%')
            )
            ->when($request->has('sort'), function (Builder $q) use ($request) {
                $direction = $request->input('sort_direction', 'asc') === 'asc' ? 'asc' : 'desc';

                return match ($request->input('sort')) {
                    'name' => $q->orderBy('name', $direction),
                    'date' => $q->orderBy('created_at', $direction),
                    'size' => $q->orderBy('size', $direction),
                    default => $q
                };
            })
            ->paginate(50);

        return LibraryResource::collection($libraries);
    }

    public function show($id)
    {
        $library = Library::findOrFail($id);

        return FileDetailsDTO::fromLibrary($library);
    }

    public function dependants($id)
    {
        $library = Library::query()->with('versions', function ($q) {
            $q->orderBy('published_at')->with('versionable');
        })->findOrFail($id);

        return $library->versions->groupBy(fn(Version $v) => $this->getVersionableIdentifier($v))
            ->mapWithKeys(function (Collection $v, string $k) {
                $result = $k === GameVersion::class ? ['name' => 'Minecraft'] : ['name' => $v->first()->versionable->name];
                $result['versions'] = $v;
                return [$k => $result];
            })
            ->values();
    }

    private function getVersionableIdentifier(Version $version)
    {
        return $version->versionable_type === GameVersion::class
            ? $version->versionable_type
            : $version->versionable_type.$version->versionable_id;
    }
}
