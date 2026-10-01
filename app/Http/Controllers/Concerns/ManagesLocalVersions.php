<?php

namespace App\Http\Controllers\Concerns;

use App\Models\File;
use App\Models\Version;

trait ManagesLocalVersions
{
    /**
     * Perform a destroy operation
     *
     * @param class-string $model
     * @param string $versionId
     * @return \Illuminate\Http\JsonResponse
     */
    protected function doDestroy(string $model, string $versionId)
    {
        $version = Version::query()->forMorph($model)->findOrFail($versionId);

        $version->forceRemove();

        return response()->json(null, 204);
    }

    /**
     * Perform a destroy file operation
     *
     * @param class-string $model
     * @param string $versionId
     * @param string $fileId
     * @return \Illuminate\Http\JsonResponse
     */
    protected function doDestroyFile(string $model, string $versionId, string $fileId)
    {
        $file = File::query()
            ->withWhereHas('version', fn($q) => $q->forMorph($model)->where('id', $versionId))
            ->findOrFail($fileId);

        $file->forceRemove();

        return response()->json(null, 204);
    }
}
