<?php

namespace App\API\Platform;

use App\API\Contracts\BaseThirdPartyApi;
use App\API\DTO\AuthorDTO;
use App\API\DTO\CategoryDTO;
use App\API\DTO\DependencyDTO;
use App\API\DTO\FileDTO;
use App\API\DTO\LoaderDTO;
use App\API\DTO\Modpack\ModpackInstallProfileDTO;
use App\API\DTO\ProjectDTO;
use App\API\DTO\VersionDTO;
use App\API\McaHttp;
use App\API\McaResponse;
use App\API\Platform\Curseforge\CurseforgeResponseTransformer;
use App\API\Requests\GetVersionsRequest;
use App\API\Requests\SearchProjectsRequest;
use App\API\ThirdPartyApiResponse;
use App\Enums\EProjectType;
use App\Exceptions\UnsupportedApiMethodException;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class Curseforge extends BaseThirdPartyApi
{
    private const MINECRAFT_GAME_ID = 432;
    private const PRIMARY_CATEGORY_MAP = [
        6 => EProjectType::MOD,
        4546 => EProjectType::CUSTOMIZATION,
        6552 => EProjectType::SHADER,
        4471 => EProjectType::MODPACK,
        6945 => EProjectType::DATAPACK,
        5 => EProjectType::PLUGIN,
        4559 => EProjectType::ADDON,
        12 => EProjectType::RESOURCE_PACK,
        17 => EProjectType::WORLD,
    ];
    private const MOD_LOADERS = ['Forge', 'Fabric', 'Quilt', 'NeoForge'];
    private const MOD_LOADERS_EXTRA = [
        'Cauldron', 'LiteLoader', 'Risugami\'s ModLoader', 'Flint Loader', 'Rift'
    ];
    private const SHADER_LOADERS = ['Canvas', 'Iris', 'OptiFine', 'Vanilla'];
    private const SEARCH_SORT_FIELDS = [
        ['id' => 6, 'name' => 'Total Downloads'],
        ['id' => 2, 'name' => 'Popularity'],
        ['id' => 1, 'name' => 'Featured'],
        ['id' => 3, 'name' => 'Last Updated'],
        ['id' => 4, 'name' => 'Name'],
//        ['id' => 5, 'name' => 'Author'],
//        ['id' => 7, 'name' => 'Category'],
//        ['id' => 8, 'name' => 'Game Version'],
//        ['id' => 9, 'name' => 'Early Access'],
        ['id' => 10, 'name' => 'Featured Released'],
        ['id' => 11, 'name' => 'Released Date'],
//        ['id' => 12, 'name' => 'Rating'],
    ];

    public function __construct(private McaHttp $http, SettingsService $settings)
    {
        $apiKey = $settings->get('platforms.curseforge.api_key');

        if (! $apiKey) {
            $this->setDisabled('missing API key');
            return;
        }

        $this->http->setBaseUrl('https://api.curseforge.com');
        $this->http->setHeaders([
            'x-api-key' => $apiKey
        ]);
    }

    public static function id(): string
    {
        return 'curseforge';
    }

    public static function name(): string
    {
        return 'Curseforge';
    }

    public static function themeColor(): string
    {
        return '#f16436';
    }

    public static function registerSettings(SettingsService $settings)
    {
        $settings->registerSetting('platforms.curseforge.api_key', '', ['string'], 'Curseforge API key');
    }

    public static function configureRequest(string $request)
    {
        if ($request === SearchProjectsRequest::class) {
            $request::configure(Curseforge::class, [
                [
                    'key' => 'project_type',
                    'target_key' => 'classId',
                    'validation' => [
                        Rule::enum(EProjectType::class)->only(Curseforge::getAvailableProjectTypes())
                    ],
                    'transform_fn' => fn($v) => Curseforge::projectTypeToPrimaryCategoryId(EProjectType::from($v)),
                    'options' => Curseforge::getAvailableProjectTypes()
                ],
                [
                    'key' => 'query',
                    'target_key' => 'searchFilter',
                    'validation' => ['string', 'max:200'],
                    'max' => 200,
                ],
                [
                    'key' => 'game_versions',
                    'target_key' => 'gameVersions',
                    'transform_fn' => fn(array $v) => sprintf('[%s]', implode(',', array_map(fn($ver) => sprintf('"%s"', $ver), $v))),
                    'max' => 4,
                ],
                [
                    'key' => 'loaders',
                    'target_key' => 'modLoaderTypes',
                    'transform_fn' => fn(Collection $v) => sprintf('[%s]', $v->map(fn($loader) => Curseforge::getLoaderTypeId($loader))->filter()->join(',')),
                    'max' => 5,
                ],
                [
                    'key' => 'categories',
                    'target_key' => 'categoryIds',
                    'transform_fn' => fn($v) => sprintf('[%s]', implode(',', $v)),
                    'max' => 3,
                ],
                [
                    'key' => 'sort_by',
                    'target_key' => 'sortField',
                    'validation' => [
                        Rule::in(array_map(fn($field) => $field['id'], Curseforge::getProjectSearchFields()))
                    ],
                    'options' => Curseforge::getProjectSearchFields()
                ],
                [
                    'key' => 'page',
                    'target_key' => 'index',
                    'validation' => ['integer', 'min:1', 'max:200'],
                    'transform_fn' => fn($v = 1) => $v * 50 - 50,
                    'max' => 10000 / 50 // 200
                ]
            ]);
        }
        elseif ($request === GetVersionsRequest::class) {
            $request::configure(Curseforge::class, [
                [
                    'key' => 'game_versions',
                    'target_key' => 'gameVersion',
                    'transform_fn' => fn($v) => Arr::first($v),
                    'max' => 1,
                ],
                [
                    'key' => 'loaders',
                    'target_key' => 'modLoaderType',
                    'transform_fn' => fn(Collection $v) => Curseforge::getLoaderTypeId($v->first()),
                    'max' => 1,
                ],
                [
                    'key' => 'page',
                    'target_key' => 'index',
                    'transform_fn' => fn($v = 1) => $v * 50 - 50,
                    'max' => 10000 / 50 // 200
                ]
            ]);
        }
    }

    /**
     * @url{https://docs.curseforge.com/#search-mods}
     *
     * @param SearchProjectsRequest $options
     * @return ThirdPartyApiResponse
     */
    public function search(SearchProjectsRequest|array $options): ThirdPartyApiResponse
    {
        $response = $this->http->get('/v1/mods/search', array_merge($this->getOptions($options), [
            'gameId' => self::MINECRAFT_GAME_ID,
            'sortOrder' => 'desc'
        ]));

        return (new ThirdPartyApiResponse(
            CurseforgeResponseTransformer::collection(ProjectDTO::class, $response->json('data')),
            $response->isCached()
        ))->withPagination(CurseforgeResponseTransformer::toPagination($response->json('pagination')));
    }

    public function getProject($id, array $options = []): ThirdPartyApiResponse
    {
        $response = $this->http->get('/v1/mods/'.$id);
        $description = $this->http->get("/v1/mods/$id/description");

        return (new ThirdPartyApiResponse(
            CurseforgeResponseTransformer::toProjectDTO($response->json('data'), $description->json('data')),
            $response->isCached() && $description->isCached()
        ));
    }

    public function getProjects(array $ids, array $options = []): ThirdPartyApiResponse
    {
        $response = $this->http->post('/v1/mods', ['modIds' => $ids]);

        return new ThirdPartyApiResponse(
            CurseforgeResponseTransformer::collection(ProjectDTO::class, $response->json('data')),
            $response->isCached()
        );
    }

    public function getProjectVersions(string $projectId, GetVersionsRequest|array $options): ThirdPartyApiResponse
    {
        $response = $this->http->get("/v1/mods/$projectId/files", $this->getOptions($options));

        return (new ThirdPartyApiResponse(
            CurseforgeResponseTransformer::collection(VersionDTO::class, $response->json('data')),
            $response->isCached()
        ))->withPagination(CurseforgeResponseTransformer::toPagination($response->json('pagination')));
    }

    public function getProjectDependencies(string $projectId, array $options = []): ThirdPartyApiResponse
    {
        throw new UnsupportedApiMethodException();
    }

    public function getProjectDependants(string $projectId, array $options = []): ThirdPartyApiResponse
    {
        throw new UnsupportedApiMethodException();
    }

    public function getVersion(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse
    {
        $version = $this->http->get("/v1/mods/$projectId/files/{$versionId}");
        if (! isset($options['without_changelog'])) {
            $changelog = $this->http->get("/v1/mods/$projectId/files/{$versionId}/changelog");
        }

        return new ThirdPartyApiResponse(
            CurseforgeResponseTransformer::toVersionDTO(
                $version->json('data'),
                isset($changelog) ? $changelog->json('data') : null
            ),
            $version->isCached() && isset($changelog) ? $changelog->isCached() : true
        );
    }

    public function getVersions(array $ids, array $options = []): ThirdPartyApiResponse
    {
        $response = $this->http->post("/v1/mods/files", array_merge([
            'fileIds' => $ids
        ], $options));

        // docs say this is supposed to include pagination, but it doesn't
        return new ThirdPartyApiResponse(
            CurseforgeResponseTransformer::collection(VersionDTO::class, $response->json('data')),
            $response->isCached()
        );
    }

    public function getVersionsFromHashes(array $hashes, string $algorithm, array $options = []): ThirdPartyApiResponse
    {
        throw new UnsupportedApiMethodException();
    }

    public function getVersionFiles(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse
    {
        // This only returns the primary file
        $response = $this->http->get("/v1/mods/$projectId/files/{$versionId}");

        // Get all additional files
        $additionalFiles = $this->getAdditionalFiles($projectId, $versionId);

        // If there are no additional files, return immediately
        if (empty($additionalFilesData = $additionalFiles->json('data'))) {
            return new ThirdPartyApiResponse(
                CurseforgeResponseTransformer::collection(FileDTO::class, [$response->json('data')], ['primary' => true]),
                $response->isCached()
            );
        }

        // If there are, re-request full file data
        $extraFiles = $this->getVersions(array_map(fn(array $file) => $file['id'], $additionalFilesData));

        // Build the response, and mark the primary file
        return new ThirdPartyApiResponse(
            new Collection([
                CurseforgeResponseTransformer::toFileDTO($response->json('data'), true),
                // we only want files
                ...$extraFiles->getData()->map(fn(VersionDTO $v) => $v->files)->flatten(1)
            ]),
            $response->isCached() && $additionalFiles->isCached() && $extraFiles->isCached()
        );
    }

    public function getVersionDependencies(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse
    {
        $version = $this->getVersion($projectId, $versionId, ['without_changelog' => true]);
        $dependencies = $version->getData()->dependencies;

        $deps = $this->getProjects($dependencies->map(fn(DependencyDTO $dep) => $dep->projectId)->toArray());

        $deps->getData()->each(function (ProjectDTO $project) use ($dependencies) {
            /** @var DependencyDTO $dep */
            $dep = $dependencies->first(fn(DependencyDTO $d) => $d->projectId === $project->id);
            if ($dep) $project->setDependencyTypeFromString($dep->type);
        });

        return $deps;
    }

    public function getVersionDependants(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse
    {
        throw new UnsupportedApiMethodException();
    }

    public function getProjectAuthors($projectId): ThirdPartyApiResponse
    {
        $response = $this->http->get('/v1/mods/'.$projectId);

        return new ThirdPartyApiResponse(
            CurseforgeResponseTransformer::collection(AuthorDTO::class, $response->json('data.authors')),
            $response->isCached()
        );
    }

    public function getCategories(array $options = []): Collection
    {
        $categories = $this->http->get('/v1/categories', [
            'gameId' => self::MINECRAFT_GAME_ID,
//            'classId' => 6,
//            'classesOnly' => 'true'
        ]);

        return (new ThirdPartyApiResponse(
            CurseforgeResponseTransformer::collection(CategoryDTO::class, $categories->json('data')),
            $categories->isCached()
        ))->getData();
    }

    /**
     * Get a collection of available loaders.
     * Curseforge API does not provide a list of available loaders, so this is hardcoded.
     *
     * @return Collection<LoaderDTO>
     */
    public function getLoaders(): Collection
    {
        return collect(array_merge(
            array_map(fn(string $loader) => CurseforgeResponseTransformer::toLoaderDTO(
                $loader, $loader, collect([EProjectType::MOD, EProjectType::MODPACK])
            ), self::MOD_LOADERS),
            array_map(fn(string $loader) => CurseforgeResponseTransformer::toLoaderDTO(
                $loader, $loader, collect()
            ), [...self::MOD_LOADERS_EXTRA, ...self::SHADER_LOADERS])
        ));
    }

    public function getAllProjectVersions(string $projectId, array $options = []): ThirdPartyApiResponse
    {
        $keepGoing = true;
        $allFiles = [];
        $index = 0;

        while ($keepGoing) {
            $response = $this->http->get(
                "/v1/mods/$projectId/files",
                array_merge(['index' => $index], $options)
            );

            $files = data_get($response->getData(), 'data');
            if (! empty($files)) {
                array_push($allFiles, ...$files);
                $index += 50;
            }
            if (count($files) < 50 || $index >= 10000) $keepGoing = false;
        }

        return new ThirdPartyApiResponse(
            CurseforgeResponseTransformer::collection(VersionDTO::class, $allFiles),
            false
        );
    }

    public function getProjectVersionsToDate(string $projectId, Carbon $date): Collection
    {
        $versions = $this->getProjectVersions($projectId, []);
        $hasVersionPublishedBeforeDate = fn(Collection $c) => $c->contains(fn(VersionDTO $v) => $v->publishedAt->isBefore($date));

        if ($hasVersionPublishedBeforeDate($versions->getData())) {
            return $versions->getData();
        }

        // has pagination
        if ($versions->getPagination()) {
            $allVersions = $versions->getData();

            while ($versions->getPagination()->hasMore()) {
                $versions = $this->getProjectVersions($projectId, ['index' => $versions->getPagination()->getIndex()]);
                $allVersions->push(...$versions->getData());

                if ($hasVersionPublishedBeforeDate($versions->getData())) {
                    return $allVersions;
                }
            }
        }

        return $versions->getData();
    }

    public function getProjectVersionsForGameVersions(string $projectId, array $gameVersions, array $options = []): ThirdPartyApiResponse
    {
        $versions = [];
        $versionIds = [];
        $cached = true;

        foreach ($gameVersions as $gameVersion) {
            $keepGoing = true;
            $index = 0;

            while ($keepGoing) {
                $response = $this->getProjectVersions($projectId, ['gameVersion' => $gameVersion, 'index' => $index]);

                /** @var VersionDTO $version */
                foreach ($response->getData() as $version) {
                    if (! in_array($version->remoteId, $versionIds)) {
                        $versionIds[] = $version->remoteId;
                        $versions[] = $version;
                    }
                }

                $index += $response->getPagination()->perPage;
                if ($response->isCached() === false) $cached = false;
                if (! $response->getPagination()->hasMore()) $keepGoing = false;
            }
        }

        return new ThirdPartyApiResponse(new Collection($versions), $cached);
    }

    private function getAdditionalFiles($projectId, $versionId): McaResponse
    {
        $http = app(McaHttp::class);

        return $http
            ->setHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36'
            ])
            ->get("https://www.curseforge.com/api/v1/mods/$projectId/files/$versionId/additional-files");
    }

    public function makeCategoryTree(Collection $categories): Collection
    {
        list($primary, $secondary) = $categories
            // filter out project types (mods, worlds, resourcepacks etc.)
            ->filter(fn(CategoryDTO $c) => $c->parentId !== null)
            ->partition(fn(CategoryDTO $c) => in_array($c->parentId, array_keys(self::PRIMARY_CATEGORY_MAP)));

        /** @var CategoryDTO $primaryCategory */
        foreach ($primary as $primaryCategory) {
            $projectType = self::primaryCategoryIdToProjectType($primaryCategory->parentId);
            $primaryCategory->projectTypes = collect([$projectType]);
            $primaryCategory->children = $secondary->filter(fn(CategoryDTO $c) => $c->parentId === $primaryCategory->id);
            $primaryCategory->children->each(fn(CategoryDTO $c) => $c->projectTypes = collect([$projectType]));
        }

        return $primary;
    }

    public function isModpackFile(string $fileName): bool
    {
        return Str::endsWith($fileName, '.zip');
    }

    public function parseModpackInstallProfile(string $filePath): ModpackInstallProfileDTO|false
    {
        $json = PlatformCommons::tryOpenAndParseModpackInstallProfile($filePath, 'manifest.json');
        if ($json === false) return false;

        try {
            return CurseforgeResponseTransformer::toModpackInstallProfileDTO($json);
        } catch (\Exception $e) {
            return false;
        }
    }

    // -----------------
    // ----- OTHER -----
    // -----------------

    public static function getAvailableProjectTypes(): array
    {
        return array_values(self::PRIMARY_CATEGORY_MAP);
    }

    public static function isModLoader(string $name): bool
    {
        return in_array(
            $name,
            [...self::MOD_LOADERS, ...self::MOD_LOADERS_EXTRA, ...self::SHADER_LOADERS],
            true
        );
    }

    /**
     * Get Curseforge enum value from loader name.
     * @see https://docs.curseforge.com/rest-api/#tocS_ModLoaderType
     * Cauldron and LiteLoader do not work (always return 0 results).
     *
     * @param $loaderName
     * @return int|null
     */
    public static function getLoaderTypeId($loaderName): ?int
    {
        return match ($loaderName) {
            // 'any' => 0,
            'Forge' => 1,
//            'Cauldron' => 2,
//            'LiteLoader' => 3,
            'Fabric' => 4,
            'Quilt' => 5,
            'NeoForge' => 6,
            default => null
        };
    }

    /**
     * Get project type from category ("classId").
     *
     * @param $id
     * @param EProjectType|null $default
     * @return mixed
     */
    public static function primaryCategoryIdToProjectType($id, ?EProjectType $default = EProjectType::OTHER): mixed
    {
        return self::PRIMARY_CATEGORY_MAP[(int)$id] ?? $default;
    }

    public static function projectTypeToPrimaryCategoryId(EProjectType $projectType)
    {
        return array_search($projectType, self::PRIMARY_CATEGORY_MAP, true);
    }

    public static function getProjectSearchFields(): array
    {
        return self::SEARCH_SORT_FIELDS;
    }

    public static function getCurseforgeHashAlgo($algo): string
    {
        return match($algo) {
            1 => 'sha1',
            2 => 'md5'
        };
    }

    public static function getFileRelationType($type): string
    {
        return match((int)$type) {
            1, 6 => 'embedded', // 6 = include
            2, 4 => 'optional', // 4 = tool
            3 => 'required',
            5 => 'incompatible',
            default => throw new \RuntimeException('Invalid file relation type: '.$type)
        };
    }
}
