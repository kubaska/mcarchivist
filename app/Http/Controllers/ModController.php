<?php

namespace App\Http\Controllers;

use App\API\DTO\AuthorDTO;
use App\API\DTO\FileDTO;
use App\API\DTO\LoaderDTO;
use App\API\DTO\VersionDTO;
use App\API\DTO\ProjectDTO;
use App\API\Requests\GetVersionsRequest;
use App\API\Requests\SearchProjectsRequest;
use App\Enums\EProjectType;
use App\Enums\ProjectDependencyType;
use App\Enums\VersionType;
use App\Jobs\ArchiveProject;
use App\Jobs\RevalidateVersionJob;
use App\Mca\ApiManager;
use App\Models\Author;
use App\Models\File;
use App\Models\GameVersion;
use App\Models\Loader;
use App\Models\MasterProject;
use App\Models\Project;
use App\Models\Ruleset;
use App\Models\Version;
use App\Resources\ArchiveRuleResource;
use App\Resources\JobStatusResource;
use App\Rules\PresentWithoutRule;
use App\Rules\ValidPlatformRule;
use App\Services\JobService;
use App\Services\McaArchiver;
use App\Services\RulesetService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;

class ModController extends Controller
{
    public function __construct(private ApiManager $apiManager)
    {
    }

    public function index(Request $request)
    {
        if ($request->boolean('archived_only')) {
            $this->validate($request, [
                'platform' => ['string', new ValidPlatformRule()],
                'query' => ['string', 'min:3', 'max:200'],
                'exclude_ids' => ['array'], // local ids
                'exclude_ids.*' => ['int'],
                // exclude a singular record; array must be of shape: [platform, remote_id]
                'exclude_remote' => ['array', 'size:2'],
                'exclude_remote.0' => ['string', new ValidPlatformRule()],
                'exclude_remote.1' => ['string'],
                'project_type' => ['int', new Enum(EProjectType::class)],
                'game_versions' => ['array'],
                'game_versions.*' => ['string'],
                'loaders' => ['array'],
                'loaders.*' => ['int'],
                'categories' => ['array'],
                'categories.*' => ['int'],
            ]);

            $projects = MasterProject::query()
                ->withWhereHas('projects', function (Builder $q) use ($request) {
                    $q->when($request->has('platform'), fn(Builder $q) => $q->where('platform', $request->input('platform')));
                    $q->with(['archive_rules', 'authors', 'categories', 'project_types'])->limit(1);
                })
                ->withCount(['projects', 'versions'])
                ->when($request->exists('query'),
                    fn(Builder $q) => $q->whereLike('name', '%'.$request->get('query').'%', false)
                )
                ->when($request->exists('exclude_ids'), function (Builder $q) use ($request) {
                    $q->whereNotIn('id', (array) $request->get('exclude_ids'));
                })
                ->when($request->exists('exclude_remote'), function (Builder $q) use ($request) {
                    $exclude = (array) $request->get('exclude_remote');
                    $q->whereDoesntHave('projects', fn(Builder $q) => $q->where('platform', $exclude[0])->where('remote_id', $exclude[1]));
                })
                ->when($request->exists('project_type'), function (Builder $q) {
                    $q->whereHas('projects', function (Builder $q) {
                        $q->whereHas('project_types', fn(Builder $q) => $q->where('type', request()->get('project_type')));
                    });
                })
                ->when($request->exists('game_versions'), function (Builder $q) {
                    $q->whereHas('projects', function (Builder $q) {
                        $q->whereHas('versions', function (Builder $q) {
                            $q->whereHas('game_versions', fn(Builder $q) => $q->whereIn('name', request()->array('game_versions')));
                        });
                    }, '=', count(request()->array('game_versions')));
                })
                ->when($request->exists('loaders'), function (Builder $q) {
                    $q->whereHas('projects', function (Builder $q) {
                        $q->whereHas('versions', function (Builder $q) {
                            $q->whereHas('loaders', fn(Builder $q) => $q->whereIn('id', request()->array('loaders')));
                        });
                    }, '=', count(request()->array('loaders')));
                })
                ->when($request->exists('categories'), function (Builder $q) {
                    $q->whereHas('projects', function (Builder $q) {
                        $q->whereHas('categories', function (Builder $q) {
                            $q->whereIn('id', request()->array('categories'));
                        }, '=', count(request()->array('categories')));
                    });
                })
                ->when($request->has('sort_by'), function (Builder $q) use ($request) {
                    return match($request->input('sort_by')) {
                        'oldest' => $q->orderBy('id'),
                        'latest' => $q->orderByDesc('id'),
                        'name' => $q->orderBy('name'),
                        'downloads' => $q->orderByDesc(
                            Project::query()
                                ->when($request->has('platform'), fn(Builder $q) => $q->where('platform', $request->input('platform')))
                                ->selectRaw('SUM(downloads)')
                                ->whereColumn('master_projects.id', 'projects.master_project_id')
                        ),
                        default => $q
                    };
                })
                ->paginate(50);

            return [
                'cached' => false,
                'data' => $projects->map(fn(MasterProject $p) => ProjectDTO::fromLocal($p, $p->projects->first())),
                ...$this->withPaginatorMeta($projects)
            ];
        }

        $this->validate($request, ['platform' => ['required', 'string', new ValidPlatformRule()]]);
        $api = $this->apiManager->get($request->get('platform'));
        $remoteProjects = $api->search(new SearchProjectsRequest($request));

        $localProjects = Project::query()
            ->where('platform', $api->id())
            ->whereIn('remote_id', $remoteProjects->getData()->pluck('id'))
            ->with('archive_rules')
            ->with('master_project', fn(Builder $q) => $q->withCount('projects'))
            ->withCount('versions')
            ->get();

        foreach ($localProjects as $localProject) {
            /** @var ProjectDTO $apiMod */
            $apiMod = $remoteProjects->getData()->first(fn(ProjectDTO $m) => $m->id == $localProject->remote_id);
            if ($apiMod) {
                $apiMod->setArchiveRules($localProject->archive_rules);
                $apiMod->localVersionCount = $localProject->versions_count;
                $apiMod->mergedProjectsCount = $localProject->master_project->projects_count;
            }
        }

        return $remoteProjects;
    }

    public function show($id, Request $request)
    {
        if ($request->boolean('archived_only')) {
            $this->validateValues(['id' => $id], ['id' => ['required', 'int']]);

            $mp = MasterProject::query()
                ->when(
                    $request->has('project_id'),
                    fn(Builder $q) => $q->with('projects', function (Builder $q) use ($request) {
                        $q->where('id', $request->get('project_id'))->with('archive_rules');
                    }),
                    fn(Builder $q) => $q->with('preferred_project', fn(Builder $q) => $q->with('archive_rules'))
                )
                ->withCount('projects')
                ->findOrFail($id);

            $project = $mp->relationLoaded('projects') ? $mp->projects->first() : $mp->preferred_project;
            $gameVersions = GameVersion::query()->hasVersionsFor(Project::class, $project->getKey())->get(['name']);
            $loaders = Loader::query()
                ->hasVersionsFor(Project::class, $project->getKey())
                ->withWhereHas('remotes', fn(Builder $q) => $q->where('platform', $project->platform))
                ->get(['id', 'name'])
                ->map(fn(Loader $l) => LoaderDTO::fromLocal($l, $l->remotes->first()));

            $projectDTO = ProjectDTO::fromLocal($mp, $project, $gameVersions->pluck('name'), $loaders);

            return [
                'cached' => false,
                'data' => $projectDTO->toArray(),
            ];
        }

        $this->validate($request, ['platform' => ['required', 'string', new ValidPlatformRule()]]);
        $api = $this->apiManager->get($request->get('platform'));

        $local = Project::with(['archive_rules', 'master_project' => function ($q) {
            $q->withCount('projects');
        }])->getRemote($api::id(), $id)->first();
        $remote = $api->getProject($id);

        $remote->getData()->projectId = $local?->getKey();
        $remote->getData()->mergedProjectsCount = $local?->master_project?->projects_count;
        if ($local?->archive_rules) {
            $remote->getData()->setArchiveRules($local->archive_rules);
        }

        return $remote;
    }

    public function authors($id, Request $request)
    {
        if ($request->boolean('archived_only')) {
            $this->validateValues(['id' => $id], ['id' => ['required', 'int']]);

            $project = Project::query()->with('authors')->findOrFail($id);

            return [
                'cached' => false,
                'data' => $project->authors->map(fn(Author $author) => AuthorDTO::fromLocal($author))
            ];
        }

        $this->validate($request, ['platform' => ['required', 'string', new ValidPlatformRule()]]);

        $api = $this->apiManager->get($request->get('platform'));
        return $api->getProjectAuthors($id);
    }

    public function dependencies($id, Request $request)
    {
        if ($request->boolean('archived_only')) {
            $this->validateValues(['id' => $id], ['id' => ['required', 'int']]);

            $project = Project::query()->findOrFail($id);

            $depProjectIds = DB::table('dependencies as d')
                ->select('d.dependency_project_id', 'd.type')
                ->distinct()
                ->join('versions as v', 'v.id', '=', 'd.version_id')
                ->where('v.versionable_id', $project->getKey())
                ->where('v.versionable_type', Model::getActualClassNameForMorph(Project::class))
                ->get()
                ->keyBy('dependency_project_id');

            $projects = Project::query()
                ->with('master_project')
                ->findMany($depProjectIds->keys())
                ->map(fn(Project $p) => ProjectDTO::fromLocal($p->master_project, $p))
                ->sortBy('name')
                ->values()
                ->each(fn(ProjectDTO $p) => $p->setDependencyType(ProjectDependencyType::from($depProjectIds->get($p->projectId)->type)));

            return [
                'cached' => false,
                'data' => $projects
            ];
        }

        $this->validate($request, ['platform' => ['required', 'string', new ValidPlatformRule()]]);
        $api = $this->apiManager->get($request->get('platform'));
        return $api->getProjectDependencies($id);
    }

    public function dependants($id, Request $request)
    {
        if ($request->boolean('archived_only')) {
            $this->validateValues(['id' => $id], ['id' => ['required', 'int']]);

            $project = Project::query()->findOrFail($id);

            $depProjectIds = DB::table('dependencies as d')
                ->select('p.id')
                ->distinct()
                ->join('versions as v', 'v.id', '=', 'd.version_id')
                ->join('projects as p', 'p.id', '=', 'v.versionable_id')
                ->where('d.dependency_project_id', $project->getKey())
                ->where('v.versionable_type', Model::getActualClassNameForMorph(Project::class))
                ->get();

            $projects = Project::query()->with('master_project')->findMany($depProjectIds->pluck('id'))
                ->map(fn(Project $p) => ProjectDTO::fromLocal($p->master_project, $p))
                ->sortBy('name')
                ->values();

            return [
                'cached' => false,
                'data' => $projects
            ];
        }

        $this->validate($request, ['platform' => ['required', 'string', new ValidPlatformRule()]]);
        $api = $this->apiManager->get($request->get('platform'));
        return $api->getProjectDependants($id);
    }

    public function versions($id, Request $request)
    {
        $this->validate($request, [
            'loaders' => ['array'],
            'loaders.*' => ['int'],
            'game_versions' => ['array'],
            'game_versions.*' => ['string', 'exists:game_versions,name'],
            'release_types' => ['array'],
            'release_types.*' => ['integer', new Enum(VersionType::class)]
        ]);

        if ($request->boolean('archived_only')) {
            $project = Project::query()
                ->when($request->has('platform'),
                    fn(Builder $q) => $q->where('remote_id', $id)->where('platform', $request->get('platform')),
                    fn(Builder $q) => $q->where('id', $id)
                )->firstOrFail();

            if ($request->boolean('all_platforms')) {
                $project->load('master_project.projects');
            }

            $local = Version::query()
                ->with(['dependencies', 'files', 'game_versions', 'loaders'])
                ->when($request->boolean('all_platforms'),
                    fn(Builder $q) => $q->whereHasMorph(
                        'versionable', [Project::class], fn(Builder $q) => $q->whereIn('id', $project->master_project->projects->pluck('id'))
                    ),
                    fn(Builder $q) => $q->whereMorphedTo('versionable', $project)
                )
                ->when($request->exists('loaders'),
                    fn(Builder $q) => $q->whereHas('loaders', fn(Builder $q) => $q->whereIn('id', $request->array('loaders')))
                )
                ->when($request->exists('game_versions'),
                    fn(Builder $q) => $q->whereHas('game_versions', fn(Builder $q) => $q->whereIn('name', $request->array('game_versions')))
                )
                ->when($request->exists('release_types'),
                    fn(Builder $q) => $q->whereIn('type', (array)$request->get('release_types'))
                )
                ->orderByDesc('published_at')
                ->paginate(50);

            return [
                'cached' => false,
                'data' => $local->map(fn(Version $v) => VersionDTO::fromLocal($v)),
                ...$this->withPaginatorMeta($local)
            ];
        }

        $this->validate($request, ['platform' => ['required', 'string', new ValidPlatformRule()]]);
        $api = $this->apiManager->get($request->get('platform'));

        $remote = $api->getProjectVersions($id, new GetVersionsRequest($request));
        $local = Version::query()
            ->has('files')
            ->where('platform', $request->get('platform'))
            ->whereIn('remote_id', $remote->getData()->pluck('id'))
            ->get();

        // mark locally saved
        foreach ($local as $version) {
            $remote->getData()->first(fn(VersionDTO $rv) => $rv->id === $version->remote_id)?->files->each(function (FileDTO $file) use ($version) {
                if ($localFile = $version->files->first(fn(File $lf) => $lf->remote_id === $file->remoteId)) {
                    $file->setLocal(true);
                    $file->dir = $localFile->path;
                }
            });
        }

        return $remote;
    }

    public function versionArchive($id, $versionId, Request $request, JobService $jobService)
    {
        $this->validateValues(
            ['id' => $id, 'versionId' => $versionId],
            ['id' => ['required', 'string'], 'versionId' => ['required', 'string']]
        );
        $this->validate($request, [
            'platform' => ['required', 'string', new ValidPlatformRule()],
            'file_ids' => ['required', 'array'],
            'project_name' => ['string', 'max:100'],
            'project_version' => ['string', 'max:100']
        ]);

        $status = $jobService->dispatch(
            new ArchiveProject(
                $this->apiManager->get($request->get('platform'))::id(),
                $id,
                $versionId,
                $request->get('file_ids'),
                true
            ),
            sprintf("%s\n%s",
                Str::limit($request->get('project_name', sprintf('unknown (%s)', $id))),
                Str::limit($request->get('project_version', sprintf('unknown (%s)', $versionId)))
            ),
            sprintf('%s;%s', $request->get('platform'), $versionId)
        );

        return new JobStatusResource($status);
    }

    public function versionRevalidate($id, $versionId, Request $request, JobService $jobService)
    {
        if ($request->boolean('id_is_remote')) {
            $this->validate($request, ['platform' => ['required', 'string', new ValidPlatformRule()]]);

            $version = Version::findRemoteOrFail($request->get('platform'), $versionId);
        } else {
            $this->validateValues(['id' => $versionId], ['id' => ['required', 'int']]);

            $version = Version::query()->findOrFail($versionId);
        }

        if ($version->versionable_type !== Model::getActualClassNameForMorph(Project::class)) {
            return response()->json([
                'error' => 'Bad Request',
                'description' => 'Only project versions can be revalidated on this endpoint'
            ], 400);
        }

        $version->load('versionable');

        $status = $jobService->dispatch(
            new RevalidateVersionJob($version->withoutRelations()),
            sprintf("%s\n%s", Str::limit($version->versionable->name), Str::limit($version->version)),
            sprintf('%s;%s', $version->platform, $version->remote_id)
        );

        return new JobStatusResource($status);
    }

    public function versionDelete($id, $versionId, Request $request)
    {
        $version = Version::query()->with(['dependants_versions']);
        if ($request->boolean('id_is_remote')) {
            $this->validate($request, ['platform' => ['required', 'string', new ValidPlatformRule()]]);

            $version = $version->findRemoteOrFail($request->get('platform'), $versionId);
        } else {
            $this->validateValues(['id' => $versionId], ['id' => ['required', 'int']]);

            $version = $version->findOrFail($versionId);
        }

        if ($version->dependants_versions->isNotEmpty()) {
            return response()->json([
                'error' => 'Unable to remove version',
                'description' => 'Cannot remove version as another project(s) depend on it.',
            ], 422);
        }

        $version->forceRemove();

        return response()->json(null, 204);
    }

    public function fileDelete($id, $versionId, $fileId, Request $request)
    {
        $file = File::query()->with('version.dependants_versions')->findOrFail($fileId);
        $version = $file->version;

        if ($version->versionable_type !== Project::class) {
            return response()->json([
                'error' => 'Bad Request',
                'description' => 'Only project files can be removed on this endpoint'
            ], 400);
        }

        if ($request->boolean('id_is_remote')) {
            if ($version->remote_id !== $versionId && $version->platform !== (int)$request->get('platform'))
                abort(400);
        } else {
            if ($version->id !== (int)$versionId)
                abort(400);
        }

        if ($file->primary && $version->dependants_versions->isNotEmpty()) {
            return response()->json([
                'error' => 'Unable to remove file',
                'description' => 'Cannot remove file as another project(s) depend on it.',
            ], 422);
        }

        $file->forceRemove($version->getStorageArea());

        if ($version->files->isEmpty()) {
            $version->forceRemove();
        }

        return response()->json(null, 204);
    }

    public function versionDependencies($id, $versionId, Request $request)
    {
        if ($request->boolean('archived_only')) {
            $this->validateValues(['id' => $versionId], ['id' => ['required', 'int']]);

            $version = Version::query()
                ->with('dependencies', function (Builder $q) {
                    $q->with(['master_project', 'authors', 'categories', 'project_types']);
                })
                ->with('dependencies_versions')
                ->findOrFail($versionId);

            if ($version->versionable_type !== Project::class) {
                return response()->json([
                    'error' => 'Dependency list unavailable',
                    'description' => 'Dependency list is unavailable for this project type.'
                ], 400);
            }

            $projects = $version->dependencies->map(function (Project $p) {
                $dto = ProjectDTO::fromLocal($p->master_project, $p);
                $dto->setDependencyType(ProjectDependencyType::from($p->pivot->type));
                return $dto;
            });
            $projects->each(function (ProjectDTO $p) use ($version) {
                $p->setDependencyVersions(
                    $version->dependencies_versions
                        ->filter(fn(Version $v) => $v->versionable_id === $p->projectId)
                        ->map(fn(Version $v) => VersionDTO::fromLocal($v))
                        ->values()
                );
            });

            return [
                'cached' => false,
                'data' => $projects->toArray()
            ];
        }

        $this->validate($request, ['platform' => ['required', 'string', new ValidPlatformRule()]]);
        $api = $this->apiManager->get($request->get('platform'));
        return $api->getVersionDependencies($id, $versionId);
    }

    public function versionDependants($id, $versionId, Request $request)
    {
        if ($request->boolean('archived_only')) {
            $this->validateValues(['id' => $versionId], ['id' => ['required', 'int']]);

            $version = Version::query()
                ->with('dependants_versions')
                ->findOrFail($versionId);

            if ($version->versionable_type !== Project::class) {
                return response()->json([
                    'error' => 'Dependant list unavailable',
                    'description' => 'Dependant list is unavailable for this project type.'
                ], 400);
            }

            $dependants = Project::query()
                ->whereIn('id', $version->dependants_versions->pluck('versionable_id')->unique())
                ->with(['master_project', 'authors', 'categories', 'project_types'])
                ->get()
                ->map(fn(Project $p) => ProjectDTO::fromLocal($p->master_project, $p));

            $dependants->each(function (ProjectDTO $p) use ($version) {
                $p->setDependencyVersions(
                    $version->dependants_versions
                        ->filter(fn(Version $v) => $v->versionable_id === $p->projectId)
                        ->map(fn(Version $v) => VersionDTO::fromLocal($v))
                        ->values()
                );
            });

            return [
                'cached' => false,
                'data' => $dependants->toArray()
            ];
        }

        $this->validate($request, ['platform' => ['required', 'string', new ValidPlatformRule()]]);
        $api = $this->apiManager->get($request->get('platform'));
        return $api->getVersionDependants($id, $versionId);
    }

    public function versionFiles($id, $versionId, Request $request)
    {
        if ($request->boolean('archived_only')) {
            $version = Version::query()->with('files');

            if ($request->boolean('id_is_remote')) {
                $this->validate($request, ['platform' => ['required', 'string', new ValidPlatformRule()]]);

                $version = $version
                    ->where('platform', $request->get('platform'))
                    ->where('remote_id', $versionId)
                    ->firstOrFail();
            } else {
                $this->validateValues(['id' => $versionId], ['id' => ['required', 'int']]);

                $version = $version->findOrFail($versionId);
            }

            return $version->files->map(fn(File $file) => FileDTO::fromLocal($file));
        }

        $this->validate($request, ['platform' => ['required', 'string', new ValidPlatformRule()]]);
        $api = $this->apiManager->get($request->get('platform'));
        $localFiles = File::query()
            ->whereHas('version', fn(Builder $q) => $q->where('platform', $api::id())->where('remote_id', $versionId))
            ->get();

        $files = $api->getVersionFiles($id, $versionId)->getData();

        // Mark files that are already archived
        $files->each(fn(FileDTO $file) => $file->setLocal(
            $localFiles->first(fn(File $lf) => $file->id === $lf->remote_id) !== null
        ));

        return $files;
    }

    public function archive($id, Request $request, McaArchiver $archiver, RulesetService $rulesetService)
    {
        $this->validate($request, [
            'platform_id' => ['required', 'string', new ValidPlatformRule()],
            'ruleset_id' => ['integer', new PresentWithoutRule('rules'), 'exists:rulesets,id'],
            ...RulesetService::getRuleValidationRules(),
            'rules' => ['array', new PresentWithoutRule('ruleset_id')],
        ]);

        if ($request->boolean('archived_only')) {
            $this->validateValues(['id' => $id], ['id' => ['required', 'int']]);

            $project = Project::query()->findOrFail($id);
        } else {
            $project = $archiver->archiveProject($request->get('platform_id'), $id);
        }

        if ($request->exists('ruleset_id')) {
            $ruleset = Ruleset::query()->with('archive_rules')->findOrFail($request->get('ruleset_id'));
            $rules = $ruleset->archive_rules->map(fn($rule) => $rule->replicate());
            $project->archive_rules()->delete();
            $project->archive_rules()->saveMany($rules);
        } else {
            $rulesetService->saveRules($project, collect($request->get('rules')), $project->archive_rules);
        }

        $project->load('archive_rules');

        return ArchiveRuleResource::collection($project->archive_rules);
    }

    public function getRelatedProjects($id, Request $request)
    {
        $this->validate($request, [
            'platform' => ['string', new ValidPlatformRule()]
        ]);

        if ($request->has('platform')) {
            $project = Project::getRemote($request->get('platform'), $id)->first();

            // Project not archived yet
            if (! $project) return ['cached' => false, 'data' => []];
        }

        $mp = MasterProject::query()
            ->with(['projects'])
            ->findOrFail(isset($project) ? $project->master_project_id : $id);

        return [
            'cached' => false,
            'data' => $mp->projects->map(fn(Project $project) => ProjectDTO::fromLocal($mp, $project))
        ];
    }

    public function merge(Request $request, McaArchiver $archiver)
    {
        $this->validate($request, [
            'project_id' => ['required'],
            'project_is_remote' => ['required', 'boolean'],
            'project_platform' => ['required', 'string', new ValidPlatformRule()],
            'merged_project_id' => ['required', 'integer', 'exists:master_projects,id'],
            'merge_direction_reverse' => ['required', 'boolean']
        ]);

        if ($request->boolean('project_is_remote')) {
            if ($p = Project::getRemote($request->get('project_platform'), $request->get('project_id'))->first()) {
                $project = MasterProject::findOrFail($p->master_project_id);
            } else {
                $p = $archiver->archiveProject($request->get('project_platform'), $request->get('project_id'));
                $project = MasterProject::findOrFail($p->master_project_id);
            }
        } else {
            $project = MasterProject::query()->findOrFail($request->get('project_id'));
        }

        $projectToMerge = MasterProject::query()->findOrFail($request->get('merged_project_id'));

        if ($project->getKey() === $projectToMerge->getKey()) {
            return response(['error' => 'Projects can not be the same!'], 422);
        }

        if ($request->boolean('merge_direction_reverse')) {
            $projectToMerge->mergeProject($project);
        } else {
            $project->mergeProject($projectToMerge);
        }

        $project->load('preferred_project');

        return [
            'cached' => false,
            'data' => ProjectDTO::fromLocal($project, $project->preferred_project)
        ];
    }

    public function unmerge($id)
    {
        $this->validateValues(['id' => $id], ['id' => ['required', 'int']]);

        $project = Project::query()->findOrFail($id);
        $mp = MasterProject::query()->withCount('projects')->findOrFail($project->master_project_id);

        if ($mp->projects_count === 1) {
            return response(['error' => 'You can not unmerge the only remaining project!'], 422);
        }

        $mp->unmergeProject($project);

        return response(null, 204);
    }

    public function setDefault($id)
    {
        $this->validateValues(['id' => $id], ['id' => ['required', 'int']]);

        $project = Project::query()->findOrFail($id);
        $mp = MasterProject::query()->findOrFail($project->master_project_id);

        $mp->update(['preferred_project_id' => $project->getKey()]);

        return response(null, 204);
    }

    public function downloadFile($id, ?string $fileName = null)
    {
        $this->validateValues(['id' => $id], ['id' => ['required', 'int']]);

        $file = File::query()->with('version')->findOrFail($id);

        return response()->download($file->getAbsoluteFilePath($file->version->getStorageArea()), $file->original_file_name);
    }
}
