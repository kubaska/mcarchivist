<?php

namespace App\Resources;

use App\Mca\ApiManager;
use App\Models\LoaderRemote;
use App\Models\ProjectType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class LoaderResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        $apiManager = app(ApiManager::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'promoted' => $apiManager->hasLoader($this->name),
            'remotes' => $this->whenLoaded(
                'remotes',
                fn(Collection $remotes) => $remotes->reduce(function (Collection $carry, LoaderRemote $lr) {
                    $carry->put($lr->platform, [
                        'remote_id' => $lr->remote_id,
                        'project_types' => $lr->project_types->map(fn(ProjectType $type) => $type->type)
                    ]);
                    return $carry;
                }, collect()),
                []
            )
        ];
    }
}
