<?php

namespace App\Http\Controllers;

use App\API\DTO\CategoryDTO;
use App\API\Requests\Base\ThirdPartyApiRequest;
use App\Mca\ApiManager;
use App\Models\Category;
use App\Models\GameVersion;
use App\Models\Loader;
use App\Models\Ruleset;
use App\Resources\LoaderResource;
use App\Resources\RulesetResource;
use App\Services\SettingsService;
use App\Support\Utils;

class ConfigController extends Controller
{

    public function index(ApiManager $apiManager)
    {
        $categories = Category::query()
            ->whereNull('parent_category_id')
            ->whereNotNull('platform')
            ->with(['children', 'project_types'])
            ->get()
            ->map(fn(Category $c) => CategoryDTO::fromLocal($c));

        $loaders = Loader::query()->with('remotes.project_types')->get();

        $requests = [];

        foreach (Utils::getRequests() as $requestClass) {
            /** @var ThirdPartyApiRequest $class */
            $class = app($requestClass);

            $requests[$class::getRequestName()] = $class->getSettings();
        }

        return [
            'platforms' => $apiManager->getAvailablePlatforms(),
            'requests' => $requests,
            'categories' => $categories,
            'game_versions' => GameVersion::query()->orderByDesc('id')->get(),
            'loaders' => LoaderResource::collection($loaders),
            'rulesets' => RulesetResource::collection(Ruleset::query()->with('archive_rules')->get()),
            'settings' => app(SettingsService::class)->getAll()
        ];
    }
}
