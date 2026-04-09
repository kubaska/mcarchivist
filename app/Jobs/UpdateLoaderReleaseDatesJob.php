<?php

namespace App\Jobs;

use App\Jobs\Middleware\McaWithoutOverlapping;
use App\Mca\ApiManager;
use App\Models\Loader;
use App\Models\Version;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateLoaderReleaseDatesJob extends Job
{
    public function __construct(protected Loader $loader)
    {
        $this->loader = $loader->withoutRelations();
    }

    public function middleware(): array
    {
        return [
            ...parent::middleware(),
            new McaWithoutOverlapping($this->loader->getKey(), 10, $this->timeout)
        ];
    }

    public function handle()
    {
        $apiManager = app(ApiManager::class);
        $api = $apiManager->getLoader($this->loader->name);
        Log::stack(['queue', 'stack'])->info(sprintf('Updating %s release dates...', $this->loader->name));

        $versions = $this->loader->versions()
            ->whereNull('published_at')
            ->with('game_versions')
            ->get();

        if ($versions->isNotEmpty()) {
            $releaseDates = $api->getReleaseDates($versions);
            $versionIds = $versions->pluck('id')->toArray();

            $releaseDates = array_filter(
                $releaseDates,
                fn(int $id) => in_array($id, $versionIds),
                ARRAY_FILTER_USE_KEY
            );

            foreach ($versions as $version) {
                if (isset($releaseDates[$version->id])) {
                    $version->published_at = $releaseDates[$version->id];
                    $version->save();
                }
            }
        }

        while($this->checkForDuplicateReleaseDates()) {
            // ...
        }
    }

    private function checkForDuplicateReleaseDates(): bool
    {
        $duplicates = Version::query()
            ->whereIn('published_at',
                Version::query()
                    ->select(['published_at'])
                    ->whereNotNull('published_at')
                    ->whereMorphedTo('versionable', $this->loader)
                    ->groupBy('published_at')
                    ->having(DB::raw('COUNT(*)'), '>', 1)
            )
            ->orderBy('id')
            ->get();

        if ($duplicates->isEmpty()) return false;

        $i = 0;
        /** @var Version $duplicate */
        foreach ($duplicates as $duplicate) {
            Log::stack(['queue', 'stack'])->info(sprintf(
                'updating %s (%s) duplicate release date: %s, + %s sec',
                $this->loader->name, $duplicate->version, $duplicate->published_at->toDateTimeString(), $i
            ));
            $duplicate->published_at = $duplicate->published_at->addSeconds($i++);
            if ($duplicate->isDirty()) $duplicate->save();
        }

        return true;
    }
}
