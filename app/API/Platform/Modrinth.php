<?php

namespace App\API\Platform;

use App\API\Contracts\BaseThirdPartyApi;
use App\API\DTO\AuthorDTO;
use App\API\DTO\CategoryDTO;
use App\API\DTO\DependencyDTO;
use App\API\DTO\FileDTO;
use App\API\DTO\LoaderDTO;
use App\API\DTO\Modpack\ModpackInstallProfileDTO;
use App\API\DTO\VersionDTO;
use App\API\DTO\ProjectDTO;
use App\API\McaHttp;
use App\API\Platform\Modrinth\ModrinthResponseTransformer;
use App\API\Requests\GetVersionsRequest;
use App\API\Requests\SearchProjectsRequest;
use App\API\RequestUtils;
use App\API\ThirdPartyApiResponse;
use App\Enums\EProjectType;
use App\Exceptions\UnsupportedApiMethodException;
use App\Mca;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class Modrinth extends BaseThirdPartyApi
{
    protected const PAGINATOR_FIELDS = ['offset', 'limit', 'total_hits'];
    private const SEARCH_SORT_FIELDS = [
        ['id' => 'downloads', 'name' => 'Downloads'],
        ['id' => 'relevance', 'name' => 'Relevance'],
        ['id' => 'newest', 'name' => 'Newest'],
        ['id' => 'updated', 'name' => 'Updated'],
        ['id' => 'follows', 'name' => 'Follows'],
    ];

    public function __construct(private McaHttp $http)
    {
        $this->http->setBaseUrl('https://api.modrinth.com/v2');
        $this->http->setUserAgent(Mca::getApplicationIdentifier());
    }

    public static function id(): string
    {
        return 'modrinth';
    }

    public static function name(): string
    {
        return 'Modrinth';
    }

    public static function themeColor(): string
    {
        return '#00af5c';
    }

    public static function configureRequest(string $request)
    {
        if ($request === SearchProjectsRequest::class) {
            $request::configure(Modrinth::class, [
                [
                    'key' => 'project_type',
                    'target_key' => 'facets',
                    'validation' => [
                        Rule::enum(EProjectType::class)->only(Modrinth::getAvailableProjectTypes())
                    ],
                    'transform_fn' => fn($v) => Modrinth::getFacetFromProjectType(EProjectType::tryFrom($v) ?? EProjectType::MOD),
                    'options' => Modrinth::getAvailableProjectTypes()
                ],
                [
                    'key' => 'query',
                    'target_key' => 'query',
                    'validation' => ['string', 'max:200'],
                    'max' => 200,
                ],
                [
                    'key' => 'game_versions',
                    'target_key' => 'facets',
                    'transform_fn' => fn($v) => Modrinth::transformToFacet('versions', $v, 'or')
                ],
                [
                    'key' => 'loaders',
                    'target_key' => 'facets',
                    'transform_fn' => fn(Collection $v) => Modrinth::transformToFacet('categories', $v->toArray())
                ],
                [
                    'key' => 'categories',
                    'target_key' => 'facets',
                    'transform_fn' => fn($v) => Modrinth::transformToFacet('categories', $v),
                    'max' => 10,
                ],
                [
                    'key' => 'sort_by',
                    'target_key' => 'index',
                    'validation' => [
                        'string',
                        Rule::in(array_map(fn($field) => $field['id'], Modrinth::getProjectSearchFields()))
                    ],
                    'options' => Modrinth::getProjectSearchFields()
                ],
                [
                    'key' => 'page',
                    'target_key' => 'offset',
                    'validation' => ['integer', 'min:1'],
                    'transform_fn' => fn($v = 1) => $v * 50 - 50,
                ]
            ]);
            $request::configureAfterTransform(Modrinth::class, function (array $options) {
                // Merge facets
                $options['facets'] = sprintf('[%s]', implode(',', $options['facets']));

                return $options;
            });
        }
        elseif ($request === GetVersionsRequest::class) {
            $request::configure(Modrinth::class, [
                [
                    'key' => 'game_versions',
                    'target_key' => 'game_versions',
                    'transform_fn' => fn($v) => Modrinth::transformToFacet('versions', $v)
                ],
                [
                    'key' => 'loaders',
                    'target_key' => 'loaders',
                    'transform_fn' => fn(Collection $v) => Modrinth::transformToFacet('categories', $v->toArray())
                ]
            ]);
        }
    }

    public function search(SearchProjectsRequest|array $options): ThirdPartyApiResponse
    {
        $response = $this->http->get('/search', array_merge($this->getOptions($options), [
            'limit' => 50,
        ]));

        return (new ThirdPartyApiResponse(
            ModrinthResponseTransformer::collection(ProjectDTO::class, $response->json('hits')),
            $response->isCached()
        ))->withPagination(ModrinthResponseTransformer::toPagination(Arr::only($response->json(), self::PAGINATOR_FIELDS)));
    }

    public function getProject($id, array $options = []): ThirdPartyApiResponse
    {
        $response = $this->http->get('/project/'.$id);

        return new ThirdPartyApiResponse(
            ModrinthResponseTransformer::toProjectDTO($response->json()),
            $response->isCached()
        );
    }

    public function getProjects(array $ids, array $options = []): ThirdPartyApiResponse
    {
        $response = $this->http->get('/projects', ['ids' => RequestUtils::stringifyArray($ids)]);

        return new ThirdPartyApiResponse(
            ModrinthResponseTransformer::collection(ProjectDTO::class, $response->json()),
            $response->isCached()
        );
    }

    public function getProjectVersions($projectId, GetVersionsRequest|array $options): ThirdPartyApiResponse
    {
        $response = $this->http->get("/project/$projectId/version", $this->getOptions($options));

        return new ThirdPartyApiResponse(
            ModrinthResponseTransformer::collection(VersionDTO::class, $response->json()),
            $response->isCached()
        );
    }

    public function getProjectDependencies(string $projectId, array $options = []): ThirdPartyApiResponse
    {
        $dependencies = $this->http->get("/project/$projectId/dependencies");

        return new ThirdPartyApiResponse(
            ModrinthResponseTransformer::collection(ProjectDTO::class, $projects = $dependencies->json('projects'))
                ->each(function (ProjectDTO $projectDto) use ($projects) {
                    $dep = Arr::first($projects, fn(array $project) => $project['id'] === $projectDto->remoteId);
                    // server side is available too
                    if ($dep) $projectDto->setDependencyTypeFromString($dep['client_side']);
                }),
            $dependencies->isCached()
        );
    }

    public function getProjectDependants(string $projectId, array $options = []): ThirdPartyApiResponse
    {
        throw new UnsupportedApiMethodException();
    }

    public function getVersion($projectId, $versionId, array $options = []): ThirdPartyApiResponse
    {
        $response = $this->http->get("/version/$versionId");

        return new ThirdPartyApiResponse(
            ModrinthResponseTransformer::toVersionDTO($response->json()),
            $response->isCached()
        );
    }

    public function getVersions(array $ids, array $options = []): ThirdPartyApiResponse
    {
        $response = $this->http->get('/versions', ['ids' => RequestUtils::stringifyArray($ids)]);

        return new ThirdPartyApiResponse(
            ModrinthResponseTransformer::collection(VersionDTO::class, $response->json()),
            $response->isCached()
        );
    }

    public function getVersionsFromHashes(array $hashes, string $algorithm, array $options = []): ThirdPartyApiResponse
    {
        if (! in_array($algorithm, ['sha1', 'sha512'], true)) {
            throw new UnsupportedApiMethodException(sprintf('%s algorithm is not supported', $algorithm));
        }

        $response = $this->http->post('/version_files', ['hashes' => $hashes, 'algorithm' => $algorithm]);

        return new ThirdPartyApiResponse(
            ModrinthResponseTransformer::collection(VersionDTO::class, $response->json(), preserveKeys: true),
            $response->isCached()
        );
    }

    public function getVersionFiles(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse
    {
        $response = $this->http->get("/version/$versionId");

        return new ThirdPartyApiResponse(
            ModrinthResponseTransformer::collection(FileDTO::class, $response->json('files')),
            $response->isCached()
        );
    }

    public function getVersionDependencies(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse
    {
        /** @var VersionDTO $version */
        $version = $this->getVersion($projectId, $versionId)->getData();

        return $this->getProjects($version->dependencies->map(fn(DependencyDTO $dep) => $dep->projectId)->filter()->toArray())
            ->tapTransformedData(fn(Collection $c) => $c->each(function (ProjectDTO $project) use ($version) {
                /** @var DependencyDTO $p */
                $p = $version->dependencies->first(fn(DependencyDTO $d) => $d->projectId === $project->id);

                $project->setDependencyTypeFromString($p->type);
            }));
    }

    public function getVersionDependants(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse
    {
        throw new UnsupportedApiMethodException();
    }

    public function getProjectAuthors(string $projectId): ThirdPartyApiResponse
    {
        $response = $this->http->get("/project/$projectId/members");

        return new ThirdPartyApiResponse(
            ModrinthResponseTransformer::collection(AuthorDTO::class, $response->json()),
            $response->isCached()
        );
    }

    public function getCategories(array $options = []): Collection
    {
        $response = $this->http->get('/tag/category');

        $categories = (new ThirdPartyApiResponse(
            ModrinthResponseTransformer::collection(CategoryDTO::class, $response->json()),
            $response->isCached()
        ))->getData();

        /** @var Collection $categories */
        list($unique, $dupes) = $categories->uniquePartition(fn(CategoryDTO $c) => $c->remoteId);
        $dupes->each(function (CategoryDTO $c) use ($unique) {
            $category = $unique->first(fn(CategoryDTO $cat) => $cat->remoteId === $c->remoteId);

            // Merge project types of duplicate categories
            /** @var CategoryDTO $category */
            $category->projectTypes->push(...$c->projectTypes);

            // Prefer group from categories with umbrella project type (MOD)
            if ($c->projectTypes->contains(EProjectType::MOD)) {
                $category->group = $c->group;
            }
        });

        return $unique;
    }

    public function getLoaders(): Collection
    {
        $response = $this->http->get('/tag/loader');

        return (new ThirdPartyApiResponse(
            ModrinthResponseTransformer::collection(LoaderDTO::class, $response->json()),
            $response->isCached()
        ))->getData();
    }

    public function getAllProjectVersions(string $projectId, array $options = []): ThirdPartyApiResponse
    {
        return $this->getProjectVersions($projectId, $options);
    }

    public function getProjectVersionsToDate(string $projectId, Carbon $date): Collection
    {
        // api returns all versions, no need to do any filtering
        return $this->getProjectVersions($projectId, [])->getData();
    }

    public function getProjectVersionsForGameVersions(string $projectId, array $gameVersions, array $options = []): ThirdPartyApiResponse
    {
        $result = [];
        $versions = $this->getProjectVersions($projectId, []);

        /** @var VersionDTO $file */
        foreach ($versions->getData() as $file) {
            if ($file->hasAnyGameVersion($gameVersions))
                $result[] = $file;
        }

        return new ThirdPartyApiResponse(new Collection($result), $versions->isCached());
    }

    // -----------------
    // ----- OTHER -----
    // -----------------

    public static function getAvailableProjectTypes(): array
    {
        return [EProjectType::MOD, EProjectType::MODPACK, EProjectType::RESOURCE_PACK, EProjectType::SHADER, EProjectType::PLUGIN, EProjectType::DATAPACK];
    }

    public static function getProjectType(string $type): ?EProjectType
    {
        return match ($type) {
            // "mod" is actually an umbrella type for mods, plugins and datapacks!
            'mod' => EProjectType::MOD,
            'modpack' => EProjectType::MODPACK,
            'resourcepack' => EProjectType::RESOURCE_PACK,
            'shader' => EProjectType::SHADER,
            'plugin' => EProjectType::PLUGIN,
            'datapack' => EProjectType::DATAPACK,
            default => null
        };
    }

    public static function getProjectSearchFields(): array
    {
        return self::SEARCH_SORT_FIELDS;
    }

    public static function getCategoryProjectTypes(string $type): array
    {
        if ($type === 'mod') return [
            EProjectType::MOD, EProjectType::PLUGIN, EProjectType::DATAPACK
        ];

        return [self::getProjectType($type)];
    }

    public static function getFacetFromProjectType(EProjectType $type): ?array
    {
        return in_array($type, self::getAvailableProjectTypes())
            ? self::transformToFacet('project_type', [str_replace(' ', '', $type->name())])
            : [];
    }

    public static function transformToFacet(string $key, array $values, $joinType = 'and'): array
    {
        if ($joinType === 'or') return [sprintf('[%s]', implode(',', array_map(fn($v) => sprintf('"%s:%s"', $key, Str::lower($v)), $values)))];
        return [implode(',', array_map(fn($v) => sprintf('["%s:%s"]', $key, Str::lower($v)), $values))];
//        return array_map(fn($v) => [$key => $v], $values);
//        return [Arr::map($values, fn($v) => [$key => $v])];
    }

    public static function formatLoaderName(string $name): string
    {
        return match ($name) {
            'neoforge' => 'NeoForge',
            'optifine' => 'OptiFine',
            'liteloader' => 'LiteLoader',
            'bungeecord' => 'BungeeCord',
            'modloader' => 'Risugami\'s ModLoader',
            'bta-babric' => 'BTA (Babric)',
            'nilloader' => 'NilLoader',
            default => ucwords(str_replace('-', ' ', $name))
        };
    }

    public static function formatCategoryName(string $name): string
    {
        if ($name === 'vanilla-like' || $name === 'semi-realistic')
            return ucfirst($name);

        return ucwords(str_replace('-', ' ', $name));
    }

    public function isModpackFile(string $fileName): bool
    {
        return Str::endsWith($fileName, '.mrpack');
    }

    public function parseModpackInstallProfile(string $filePath): ModpackInstallProfileDTO|false
    {
        $json = PlatformCommons::tryOpenAndParseModpackInstallProfile($filePath, 'modrinth.index.json');
        if ($json === false) return false;

        try {
            return ModrinthResponseTransformer::toModpackInstallProfileDTO($json);
        } catch (\Exception $e) {
            return false;
        }
    }
}
