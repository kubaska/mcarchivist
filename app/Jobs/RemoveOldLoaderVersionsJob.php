<?php

namespace App\Jobs;

use App\Mca\ApiManager;
use App\Models\Loader;
use App\Models\Version;
use App\Services\McaLoaderArchiver;
use Illuminate\Bus\Batchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class RemoveOldLoaderVersionsJob extends Job
{
    use Batchable;

    public function __construct(protected Loader $loader)
    {
    }

    public function middleware(): array
    {
        return [
            ...parent::middleware(),
            new WithoutOverlapping('', 10, $this->timeout)
        ];
    }

    public function handle(ApiManager $apiManager, McaLoaderArchiver $loaderArchiver)
    {
        Log::stack(['queue', 'stack'])->info(sprintf('Removing old %s loader versions', $this->loader->name));

        $api = $apiManager->getLoader($this->loader->name);

        $this->loader->versions()
            ->tap(fn(Builder $q) => $loaderArchiver->applyLoaderAutoArchivableVersionsQuery($q, 'whereNotIn', $this->loader, $api))
            ->has('files')
            ->lazyById()
            ->each(function (Version $version) {
                Log::stack(['queue', 'stack'])->info(sprintf('Removing version: %s (%s).', $version->version, $version->id));
                $version->remove();
            });
    }
}
