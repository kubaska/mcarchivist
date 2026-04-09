<?php

namespace App\API\Requests;

use App\API\Requests\Base\ThirdPartyApiRequest;
use App\Models\Loader;

class GetVersionsRequest extends ThirdPartyApiRequest
{
    public static function getRequestName(): string
    {
        return 'get_versions';
    }

    public function getRequestExposedFields(): array
    {
        return ['options', 'max'];
    }

    public function getDefaultMorphs(): array
    {
        return [
            [
                'key' => 'loaders',
                'transform_fn' => function ($v, $apiClass) {
                    return Loader::query()
                        ->withWhereHas('remotes', fn($q) => $q->where('platform', $apiClass::id()))
                        ->findMany($v)
                        ->map(fn(Loader $l) => $l->remotes->first()?->remote_id)
                        ->filter();
                }
            ]
        ];
    }
}
