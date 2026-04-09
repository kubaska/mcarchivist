<?php

namespace App\Services;

use App\API\Contracts\ThirdPartyApi;
use App\API\DTO\CategoryDTO;
use App\API\DTO\ProjectDTO;
use App\Enums\EProjectType;
use App\Mca\ApiManager;
use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CategoryService
{
    private ?Collection $projectTypeCache = null;

    public function __construct(protected ApiManager $apiManager)
    {
    }

    public function importProjectTypes(): Collection
    {
        if ($this->projectTypeCache) return $this->projectTypeCache;

        $types = ProjectType::query()->get();

        /** @var Collection $missing */
        list(, $missing) = collect(EProjectType::cases())->partition(
            fn(EProjectType $type) => $types->where('type', $type)->first() !== null
        );

        $newTypes = $missing->map(fn(EProjectType $type) => ProjectType::make(
            ['name' => Str::title($type->name()), 'type' => $type]
        ));

        $newTypes->each(fn($c) => $c->save());

        $this->projectTypeCache = $types->merge($newTypes);

        return $this->projectTypeCache;
    }

    public function importRemoteCategories()
    {
        $this->apiManager->each(function (ThirdPartyApi $api) {
            $categories = $api->getCategories();

            $categoriesTree = $api->makeCategoryTree($categories);

            $this->importRemoteCategoryTree($categoriesTree);
        });
    }

    protected function importRemoteCategoryTree(Collection $categories, ?Category $parent = null): Collection
    {
        $types = $this->importProjectTypes();
        $localCategories = new Collection;

        /** @var CategoryDTO $category */
        foreach ($categories as $category) {
            $localCategory = Category::updateOrCreate(
                ['remote_id' => $category->remoteId, 'platform' => $category->platform],
                array_merge(
                    ['name' => $category->name],
                    $category->group === false ? [] : ['group' => $category->group],
                    $parent ? ['parent_category_id' => $parent->id] : []
                )
            );

            if ($category->projectTypes !== null) {
                $localCategory->project_types()->sync($types->whereIn('type', $category->projectTypes));
            }
            $localCategories->push($localCategory);

            if ($category->children?->isNotEmpty()) {
                $children = $this->importRemoteCategoryTree($category->children, $localCategory);
                $localCategories->push(...$children);
            }
        }

        return $localCategories;
    }

    public function archiveRemoteCategories(Project $project, ProjectDTO $remoteProject)
    {
        if ($remoteProject->projectTypes->isNotEmpty()) {
            $projectTypes = collect();

            ($fetchProjectTypes = function () use (&$projectTypes, $remoteProject) {
                $projectTypes = ProjectType::query()
                    ->whereIn('type', $remoteProject->projectTypes)
                    ->get();
            })();

            if ($remoteProject->projectTypes->count() > $projectTypes->count()) {
                Log::debug(sprintf(
                    'Project %s has project types that do not exist locally, importing',
                    $remoteProject->name
                ));

                $this->importProjectTypes();
                $fetchProjectTypes();
            }

            $project->project_types()->sync($projectTypes);
        }
        else {
            Log::stack(['queue', 'stack'])->warning(sprintf('Project "%s" [%s] does not have a project type!', $project->name, $remoteProject->id));
        }

        if ($remoteProject->categories->isEmpty()) {
            return;
        }

        $api = $this->apiManager->get($project->platform);
        $localCategories = $this->importRemoteCategoryTree($api->makeCategoryTree($remoteProject->categories));

        list($categories, $mergedCategories) = $localCategories->partition(fn(Category $c) => $c->merge_with_id === null);
        $remainingCategories = $this->resolveLocallyMergedCategories($remoteProject, $mergedCategories);
        $project->categories()->sync($categories->merge($remainingCategories)->pluck('id'));
    }

    protected function resolveLocallyMergedCategories(ProjectDTO $remoteProject, Collection $categories, int $depth = 0): Collection
    {
        if ($categories->isEmpty()) return collect();

        if ($depth > 1) Log::warning(sprintf('resolveLocallyMergedCategories depth: %s. This can be optimized!!', $depth));
        if ($depth > 50) {
            Log::error(sprintf('Failed resolving local categories for project %s. Giving up after 50 tries', $remoteProject->name));
            return collect();
        }

        $localCategories = Category::query()->findMany($categories->map(fn(Category $c) => $c->merge_with_id));

        if ($categories->count() !== $localCategories->count())
            Log::stack(['queue', 'stack'])->warning('Failed to fetch all queried local categories, is local database missing some?');

        list($categories, $mergedCategories) = $localCategories->partition(fn(Category $c) => $c->merge_with_id === null);

        $categories->merge($this->resolveLocallyMergedCategories($remoteProject, $mergedCategories, ++$depth));

        return $categories;
    }
}
