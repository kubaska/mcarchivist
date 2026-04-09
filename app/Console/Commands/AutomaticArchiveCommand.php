<?php

namespace App\Console\Commands;

use App\API\Loader\Base\BaseLoader;
use App\Enums\AutomaticArchiveSetting;
use App\Enums\VersionType;
use App\Jobs\ArchiveGameVersion;
use App\Jobs\ArchiveLoaderJob;
use App\Jobs\ArchiveProjectFromRulesetJob;
use App\Jobs\RemoveOldLoaderVersionsJob;
use App\Jobs\UpdateGameVersionsIndexJob;
use App\Jobs\UpdateLoaderIndexJob;
use App\Jobs\UpdateLoaderReleaseDatesJob;
use App\Mca\ApiManager;
use App\Models\GameVersion;
use App\Models\Loader;
use App\Models\MasterProject;
use App\Models\Version;
use App\Services\JobService;
use App\Services\McaLoaderArchiver;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

class AutomaticArchiveCommand extends Command implements Isolatable
{
    protected $signature = 'mca:automatic-archive';

    protected $description = 'Runs automatic archiver';

    public function handle(
        ApiManager $manager,
        McaLoaderArchiver $loaderArchiver,
        SettingsService $settings
    )
    {
        MasterProject::query()
            ->whereHas('projects', fn(Builder $q) => $q->has('archive_rules'))
            ->whereHas('projects', fn(Builder $q) => $q
                ->where('last_version_check', '>', $this->getCheckBoundaryDate($settings, 'projects'))
                ->orWhere('version_check_available_at', '>', Carbon::now()),
                '=', 0
            )
            ->lazyById()
            ->map(fn(MasterProject $project) => new ArchiveProjectFromRulesetJob($project))
            ->tap(function (LazyCollection $c) {
                $this->makeBatch('Project Automatic Archiver', $c->toArray())?->dispatch();
            });

        if ($aas = $this->shouldDispatch($settings, 'game_versions')) {
            try {
                dispatch_now(new UpdateGameVersionsIndexJob());
            } catch (\Exception $e) {
                Log::error('Failed to update game versions index!', [$e]);
            }

            if ($aas === AutomaticArchiveSetting::ARCHIVE) {
                $versionTypes = array_map(
                    fn($type) => VersionType::fromName($type),
                    (array)$settings->get('game_versions.automatic_archive.release_types')
                );

                $components = $settings->get('game_versions.automatic_archive.components');
                $gameVersions = GameVersion::query()
                    ->official()
                    ->when(! empty($versionTypes), fn(Builder $q) => $q->whereIn('type', $versionTypes));

                if (in_array('*', $components)) {
                    $gameVersions = $gameVersions
                        ->with('version.files')
                        ->get()
                        ->filter(fn(GameVersion $gv) => ! $gv->version?->hasAllComponents($gv->version->files->pluck('component')));
                } else {
                    $gameVersions = $gameVersions
                        ->whereDoesntHave('version.files', fn(Builder $q) => $q->whereIn('component', $components))
                        ->get();
                }

                $this->makeBatch(
                    'Game Versions Automatic Archiver',
                    $gameVersions->map(fn(GameVersion $v) => new ArchiveGameVersion($v->name, $components, false))
                )?->dispatch();

                $settings->save(['game_versions.automatic_archive.last_check' => Carbon::now()]);
            }
        }

        $manager->eachLoader(function (BaseLoader $loaderApi) use ($loaderArchiver, $settings) {
            if ($aas = $this->shouldDispatch($settings, $settingPrefix = $loaderApi->getSettingPrefix())) {
                $loader = Loader::query()->where('slug', $loaderApi->slug())->first();
                if (! $loader) {
                    Log::error('Missing database instance of loader '.$loaderApi::name());
                    return;
                }

                try {
                    dispatch_now(new UpdateLoaderIndexJob($loader));

                    if ($loader->versions()->whereNull('published_at')->exists()) {
                        dispatch_now(new UpdateLoaderReleaseDatesJob($loader));
                    }
                } catch (ConnectionException $e) {
                    Log::error(sprintf('Failed to fetch %s loader manifests', $loader->name), [$e]);
                }

                if ($aas !== AutomaticArchiveSetting::ARCHIVE) return;

                $components = $settings->has($settingPrefix.'.automatic_archive.components')
                    ? $settings->get($settingPrefix.'.automatic_archive.components')
                    : ['*'];

                $versions = $loader->versions()
                    ->tap(fn(Builder $q) => $loaderArchiver->applyLoaderAutoArchivableVersionsQuery($q, 'whereIn', $loader, $loaderApi))
                    ->when($settings->has($settingPrefix.'.automatic_archive.components'),
                        fn(Builder $q) => $q->whereDoesntHave('files', fn(Builder $q) => $q->whereIn(
                            'component', $settings->get($settingPrefix.'.automatic_archive.components')
                        )),
                        fn(Builder $q) => $q->whereDoesntHave('files')
                    )
                    ->get();

                $batch = $this->makeBatch(
                    $loaderApi::name(). ' Loader Automatic Archiver',
                    $versions->map(fn(Version $v) => new ArchiveLoaderJob($v, $components, false))
                );

                if ($settings->has($settingPrefix.'.automatic_archive.remove_old')
                    && $settings->get($settingPrefix.'.automatic_archive.remove_old')
                ) {
                    $batch?->then(function () use ($loader) {
                        dispatch(new RemoveOldLoaderVersionsJob($loader));
                    });
                }

                $batch?->dispatch();

                $settings->save([$settingPrefix.'.automatic_archive.last_check' => Carbon::now()]);
            }
        });

        return 0;
    }

    private function getCheckBoundaryDate(SettingsService $settings, string $settingPrefix): Carbon
    {
        $interval = (int)$settings->get($settingPrefix.'.automatic_archive.interval');
        $unit = $settings->get($settingPrefix.'.automatic_archive.interval_unit');

        $boundary = Carbon::now();
        if ($unit === 'h') $boundary->subHours($interval);
        else $boundary->subDays($interval);

        return $boundary;
    }

    private function shouldDispatch(SettingsService $settings, string $settingPrefix): AutomaticArchiveSetting|false
    {
        if (! $settings->has($settingPrefix.'.automatic_archive')) return false;

        /** @var AutomaticArchiveSetting $aas */
        $aas = $settings->get($settingPrefix.'.automatic_archive');
        if ($aas === null || $aas === AutomaticArchiveSetting::OFF) return false;

        $lastCheck = $settings->getDate($settingPrefix.'.automatic_archive.last_check');
        if ($lastCheck === null) return $aas;

        $boundary = $this->getCheckBoundaryDate($settings, $settingPrefix);
        if ($lastCheck->isAfter($boundary)) return false;

        return $aas;
    }

    private function makeBatch(string $name, array|Collection $jobs): ?PendingBatch
    {
        JobService::cancelBatchByName($name);

        if (! count($jobs)) return null;

        return Bus::batch($jobs)
            ->name($name)
            ->allowFailures()
            ->finally(fn(Batch $batch) => JobService::onBatchCompleted($batch));
    }
}
