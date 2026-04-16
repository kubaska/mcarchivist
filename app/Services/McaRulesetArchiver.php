<?php

namespace App\Services;

use App\API\DTO\FileDTO;
use App\API\DTO\VersionDTO;
use App\Enums\FileQualifier;
use App\Mca\ApiManager;
use App\Models\ArchiveRule;
use App\Models\MasterProject;
use App\Models\Project;
use App\Models\Version;
use App\Support\Utils;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class McaRulesetArchiver
{
    public function __construct(
        protected ApiManager $apiManager,
        protected McaArchiver $archiver,
        protected GameVersionService $gameVersionService
    )
    {
    }

    public function archive(MasterProject $mp)
    {
        $mp->load(['projects' => function ($query) {
            $query->with(['archive_rules' => fn ($q) => $q->with('loader.remotes')]);
        }]);

        foreach ($mp->projects as $project) {
            $checkDate = Carbon::now();

            try {
                $api = $this->apiManager->get($project->platform);
            } catch (\Exception $e) {
                continue;
            }

            /** @var Collection $remoteVersions */
            if ($project->last_version_check) {
                $remoteVersions = $api->getProjectVersionsToDate($project->remote_id, $project->last_version_check);
            } else {
                $remoteVersions = $api->getAllProjectVersions($project->remote_id)->getData();
            }

            $remoteVersions = $remoteVersions->filter(fn(VersionDTO $v) => $v->getPrimaryFile());

            $commonHashAlgo = Utils::findCommonHashAlgo(
                $remoteVersions->map(fn(VersionDTO $v) => $v->getPrimaryFile()->hashes->getAlgos())->toArray()
            );

            if (! $commonHashAlgo) {
                Log::stack(['queue', 'stack'])->error(sprintf(
                    'Unable to find common hash algorithm for project %s, on platform %s', $project->name, $project->platform
                ));

                $project->version_check_available_at = Carbon::now()->addHours(6);
                $project->save();
                continue;
            }

            $remoteVersions = $remoteVersions
                ->each(fn(VersionDTO $v) => $v->extra = ['local' => false])
                ->keyBy(fn(VersionDTO $v) => $v->getPrimaryFile()->hashes->get($commonHashAlgo));

            $localVersions = $mp->projects
                ->map(fn(Project $p) => $p->versions()->with(['files', 'game_versions', 'loaders'])->get())->flatten(1)
                ->map(fn(Version $v) => VersionDTO::fromLocal($v))
                ->each(fn(VersionDTO $v) => $v->extra = ['local' => true])
                ->filter(fn(VersionDTO $v) => $v->getPrimaryFile())
                ->keyBy(fn(VersionDTO $v) => $v->getPrimaryFile()->hashes->get($commonHashAlgo));

            // Merge remote & local. Local takes precedence
            $versions = $remoteVersions->merge($localVersions);

            $result = $this->findMatchingVersions($project, $project->archive_rules, $versions);

            list($localVersions, $remoteVersions) = $result->partition(fn(VersionDTO $v) => $v->extra['local']);

            /** @var VersionDTO $remoteVersion */
            foreach ($remoteVersions as $remoteVersion) {
                Log::stack(['queue', 'stack'])->info('Archiving version: '.$remoteVersion->remoteId);
                $this->archiver->archiveProjectFiles(
                    $project,
                    $remoteVersion,
                    $remoteVersion->extra['all_files'] ? FileQualifier::ALL : FileQualifier::PRIMARY_ONLY,
                    $remoteVersion->extra['dependencies']
                );
            }

            // remove versions that do not fill rule criteria anymore
            $versionsToRemove = $project->versions()
                ->whereNotIn('remote_id', [
                    ...$remoteVersions->map(fn(VersionDTO $v) => $v->remoteId)->toArray(),
                    ...$localVersions->where('platform', $project->platform)->map(fn(VersionDTO $v) => $v->remoteId)->toArray()
                ])
                ->get();

            if ($versionsToRemove->isNotEmpty()) {
                Log::debug('Removing versions that do not meet archive rule criteria');

                foreach ($versionsToRemove as $version) {
                    $version->remove();
                }
            }

            $project->last_version_check = $checkDate;
            $project->version_check_available_at = null;
            $project->save();
        }
    }

    /**
     * Determine whichever versions are matching user-made rules.
     *
     * @param Project $project
     * @param Collection<ArchiveRule> $ruleset
     * @param Collection<VersionDTO> $versions
     * @return Collection
     */
    public function findMatchingVersions(Project $project, Collection $ruleset, Collection $versions): Collection
    {
        $allApplicableVersions = collect();

        /** @var ArchiveRule $rule */
        foreach ($ruleset as $rule) {
            $gameVersions = $this->gameVersionService->getVersionsBetween($rule->game_version_from, $rule->game_version_to, $rule->with_snapshots);

            foreach ($gameVersions as $gameVersion) {
                $remoteLoaderNames = $versions
                    ->map(fn(VersionDTO $v) => $v->loaders->pluck('name'))
                    ->flatten(1)
                    ->unique();

                $applicableVersions = $versions
                    ->filter(fn(VersionDTO $v) => $v->hasGameVersion($gameVersion->name))
                    ->when($rule->loader, function (Collection $c) use ($project, $rule) {
                        if ($remoteLoader = $rule->loader->remotes->firstWhere('platform', $project->platform)) {
                            return $c->filter(fn(VersionDTO $v) => $v->hasLoaderRemoteId($remoteLoader->remote_id));
                        }

                        return $c;
                    })
                    ->when(is_null($rule->release_type),
                        fn(Collection $c) => $rule->release_type_priority
                            ? $c->sortGrouping(['type', 'asc'], [['publishedAt', $rule->sorting ? 'asc' : 'desc']])
                            : $c,
                        fn(Collection $c) => $c->filter(fn(VersionDTO $mv) => $mv->type === $rule->release_type)
                    )
                    ->when(is_null($rule->release_type) && $rule->release_type_priority,
                        fn(Collection $c) => $c, // Sorted already.
                        fn(Collection $c) => $c->sortBy('publishedAt', descending: ! $rule->sorting)
                    );

                if ($rule->loader) {
                    $applicableVersions = $applicableVersions->take($rule->count);
                } else {
                    $result = new Collection();
                    foreach ($remoteLoaderNames as $loaderName) {
                        $versionsWithLoader = $applicableVersions
                            ->filter(fn(VersionDTO $v) => $v->hasLoader($loaderName))
                            ->take($rule->count);

                        $result->push(...$versionsWithLoader);
                    }

                    if ($result->count() === 0 || $result->count() < $rule->count) {
                        $versionsWithoutLoader = $applicableVersions
                            ->filter(fn(VersionDTO $v) => $v->loaders->isEmpty())
                            ->take($rule->count);

                        $result->push(...$versionsWithoutLoader);
                    }

                    $applicableVersions = $result;
                }

                /** @var VersionDTO $applicableVersion */
                foreach ($applicableVersions as $applicableVersion) {
                    $stagedVersion = $allApplicableVersions->first(
                        fn(VersionDTO $v) => $v->id === $applicableVersion->id && $v->platform === $applicableVersion->platform
                    );

                    if ($stagedVersion) {
                        if (! $stagedVersion->extra['all_files']) {
                            $stagedVersion->extra['all_files'] = $rule->all_files;
                        }
                        if ($stagedVersion->extra['dependencies']->comparePriority($rule->dependencies)) {
                            $stagedVersion->extra['dependencies'] = $rule->dependencies;
                        }
                    } else {
                        $applicableVersion->extra['all_files'] = $rule->all_files;
                        $applicableVersion->extra['dependencies'] = $rule->dependencies;
                        $allApplicableVersions->push($applicableVersion);
                    }
                }
            }
        }

        return $allApplicableVersions;
    }
}
