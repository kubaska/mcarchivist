<?php

namespace App\Http\Controllers;

use App\API\DTO\FileDetailsDTO;
use App\Models\Library;
use App\Resources\LibraryResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
}
