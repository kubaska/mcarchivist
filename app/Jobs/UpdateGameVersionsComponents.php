<?php

namespace App\Jobs;

use App\API\Mojang;
use App\Jobs\Middleware\McaWithoutOverlapping;
use App\Models\GameVersion;
use App\Models\Version;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UpdateGameVersionsComponents extends Job
{
    public function __construct(protected bool $revalidate = false)
    {
    }

    public function middleware(): array
    {
        return [
            ...parent::middleware(),
            new McaWithoutOverlapping('', 10, $this->timeout)
        ];
    }

    public function handle(Mojang $api)
    {
        $versions = Version::query()
            ->where('versionable_type', Model::getActualClassNameForMorph(GameVersion::class))
            ->when($this->revalidate === false, fn(Builder $q) => $q->whereNull('components'))
            ->latest('id')
            ->get();

        foreach ($versions as $version) {
            $manifest = $api->getVersion($version->version);

            $version->saveAvailableComponentList($manifest->getComponentNames()->toArray());
        }
    }
}
