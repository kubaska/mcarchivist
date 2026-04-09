<?php

namespace Tests\Unit\Platforms;

use App\API\Contracts\ThirdPartyApiTest;
use App\API\DTO\AuthorDTO;
use App\API\DTO\CategoryDTO;
use App\API\DTO\DependencyDTO;
use App\API\DTO\FileDTO;
use App\API\DTO\LoaderDTO;
use App\API\DTO\ProjectDTO;
use App\API\DTO\VersionDTO;
use App\API\Platform\Modrinth;
use App\Enums\EProjectType;
use App\Enums\ProjectDependencyType;
use App\Enums\VersionType;
use App\Exceptions\UnsupportedApiMethodException;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModrinthTest extends TestCase implements ThirdPartyApiTest
{
    protected Modrinth $api;

    protected const PROJECT_AE2 = [
        'client_side' => 'required',
        'server_side' => 'required',
        'game_versions' => [
            '1.18',
            '1.18.1',
            '1.18.2',
            '1.19',
            '1.19.1',
            '1.19.2',
            '1.19.3',
            '1.20.1',
            '1.20.2',
            '1.20.4',
        ],
        'id' => 'XxWD5pD3',
        'slug' => 'ae2',
        'project_type' => 'mod',
        'team' => 'CqWdb4w5',
        'organization' => NULL,
        'title' => 'Applied Energistics 2',
        'description' => 'AE2: A popular automation and storage mod',
        'body' => 'Applied Energistics 2 (AE2) is a comprehensive mod for Minecraft that introduces a unique approach to in-game inventory management. It presents a tech-based, futuristic theme, centered around the concept of using energy and technology to convert matter into energy and vice versa.',
        'body_url' => NULL,
        'published' => '2021-12-14T11:50:43.324146Z',
        'updated' => '2026-01-01T14:52:28.206343Z',
        'approved' => '2021-12-14T11:50:43.324146Z',
        'queued' => NULL,
        'status' => 'approved',
        'requested_status' => NULL,
        'moderator_message' => NULL,
        'license' => [
            'id' => 'LicenseRef-Multiple',
            'name' => '',
            'url' => 'https://github.com/AppliedEnergistics/Applied-Energistics-2#license',
        ],
        'downloads' => 3263842,
        'followers' => 1066,
        'categories' => [
            'storage',
            'technology',
            'utility',
        ],
        'additional_categories' => [],
        'loaders' => [
            'fabric',
            'forge',
            'neoforge',
        ],
        'versions' => [
            'OqkivEmV',
            'DZZbZnbH',
            'cLm6eoS7',
            // ...
        ],
        'icon_url' => 'https://cdn.modrinth.com/data/XxWD5pD3/60b001515abbf6ebf32bc2729b0ddd95d2793feb_96.webp',
        'issues_url' => 'https://github.com/AppliedEnergistics/Applied-Energistics-2/issues',
        'source_url' => 'https://github.com/AppliedEnergistics/Applied-Energistics-2',
        'wiki_url' => 'https://appliedenergistics.github.io/',
        'discord_url' => 'https://discord.gg/Zd6t9ka7ne',
        'donation_urls' => [],
        'gallery' => [
            [
                'url' => 'https://cdn.modrinth.com/data/XxWD5pD3/images/0fa0264ab3d82a7cdb872be4c4356426dd7145fe_350.webp',
                'raw_url' => 'https://cdn.modrinth.com/data/XxWD5pD3/images/0fa0264ab3d82a7cdb872be4c4356426dd7145fe.png',
                'featured' => false,
                'title' => 'Screenshot',
                'description' => NULL,
                'created' => '2022-12-28T02:28:38.379797Z',
                'ordering' => 0,
            ],
        ],
        'color' => 3553080,
        'thread_id' => 'XxWD5pD3',
        'monetization_status' => 'monetized'
    ];
    protected const PROJECT_TINKERS_CONSTRUCT = [
        'client_side' => 'required',
        'server_side' => 'required',
        'game_versions' => [
            '1.6.4',
            '1.7.10',
            '1.12.2',
            '1.16.5',
            '1.18.2',
            '1.19.2',
            '1.20.1',
        ],
        'id' => 'rxIIYO6c',
        'slug' => 'tinkers-construct',
        'project_type' => 'mod',
        'team' => 'cwCajnNu',
        'organization' => NULL,
        'title' => 'Tinkers\' Construct',
        'description' => 'Tinker a little, modify all the tools, build a smeltery, then tinker a little more. The classic modular tool mod.',
        'body' => 'Tinkers\' Construct is a mod about putting tools together in a wide variety of ways, then modifying them until they turn into something else. The tools never disappear and can be named and changed to your heart\'s desire. Once you make them, they\'re yours forever. Many different materials can be used to make your tools.',
        'body_url' => NULL,
        'published' => '2022-12-28T01:42:54.536335Z',
        'updated' => '2026-01-12T04:13:27.233744Z',
        'approved' => '2022-12-28T05:07:08.064811Z',
        'queued' => NULL,
        'status' => 'approved',
        'requested_status' => NULL,
        'moderator_message' => NULL,
        'license' => [
            'id' => 'MIT',
            'name' => 'MIT License',
            'url' => NULL,
        ],
        'downloads' => 1857009,
        'followers' => 987,
        'categories' => [
            'equipment',
            'magic',
            'technology',
        ],
        'additional_categories' => [],
        'loaders' => [
            'forge',
            'neoforge',
        ],
        'versions' => [
            'HXIzidIf',
            '3KJI35bq',
            'mNZUIBbe'
            // ...
        ],
        'icon_url' => 'https://cdn.modrinth.com/data/rxIIYO6c/4c1b02748edd2def6a2427f494735641570c34ec_96.webp',
        'issues_url' => 'https://github.com/SlimeKnights/TinkersConstruct/issues',
        'source_url' => 'https://github.com/SlimeKnights/TinkersConstruct',
        'wiki_url' => 'https://slimeknights.github.io/docs/',
        'discord_url' => 'https://discord.gg/njGrvuh',
        'donation_urls' => [],
        'gallery' => [
            [
                'url' => 'https://cdn.modrinth.com/data/rxIIYO6c/images/0fa0264ab3d82a7cdb872be4c4356426dd7145fe_350.webp',
                'raw_url' => 'https://cdn.modrinth.com/data/rxIIYO6c/images/0fa0264ab3d82a7cdb872be4c4356426dd7145fe.png',
                'featured' => false,
                'title' => 'Plate Armor',
                'description' => NULL,
                'created' => '2022-12-28T02:28:38.379797Z',
                'ordering' => 0,
            ],
            [
                'url' => 'https://cdn.modrinth.com/data/rxIIYO6c/images/101c3789a36af264a9fd9a00eb632f937a0e94f8_350.webp',
                'raw_url' => 'https://cdn.modrinth.com/data/rxIIYO6c/images/101c3789a36af264a9fd9a00eb632f937a0e94f8.png',
                'featured' => false,
                'title' => 'Ender Slime Geode',
                'description' => NULL,
                'created' => '2022-12-28T02:21:51.278145Z',
                'ordering' => 0,
            ],
            // ...
        ],
        'color' => 4408118,
        'thread_id' => 'rxIIYO6c',
        'monetization_status' => 'monetized',
    ];
    protected const PROJECT_GUIDEME = [
        'client_side' => 'required',
        'server_side' => 'required',
        'game_versions' => [
            '1.20.1',
            '1.20.4',
            '1.21.1',
            '1.21.5',
            '1.21.8',
            '1.21.10',
            '1.21.11',
        ],
        'id' => 'Ck4E7v7R',
        'slug' => 'guideme',
        'project_type' => 'mod',
        'team' => 'EresTnjy',
        'organization' => NULL,
        'title' => 'GuideME',
        'description' => 'A guidebook toolkit for mods and modpack makers alike with comfortable markdown formatting, and live 3d scenes!',
        'body' => '# GuideME This project offers the foundation for your mods or modpacks guidebook. Based on the technology powering Applied Energistics 2s guidebook, it allows you to write your guide in simple Markdown, while embedding 3d scenes straight from structure files.',
        'body_url' => NULL,
        'published' => '2025-01-17T22:35:18.397149Z',
        'updated' => '2026-01-01T15:41:32.595714Z',
        'approved' => '2025-01-20T09:16:19.463128Z',
        'queued' => '2025-01-18T01:02:00.285966Z',
        'status' => 'approved',
        'requested_status' => 'approved',
        'moderator_message' => NULL,
        'license' => [
            'id' => 'LicenseRef-Multiple-OSS-Licenses',
            'name' => '',
            'url' => 'https://github.com/AppliedEnergistics/GuideME/blob/main/LICENSE.MD',
        ],
        'downloads' => 1061811,
        'followers' => 58,
        'categories' => [
            'library',
        ],
        'additional_categories' => [
            'utility',
        ],
        'loaders' => [
            'forge',
            'neoforge',
        ],
        'versions' => [
            'bk0EGcWt',
            'xp5Eb3cc',
            'MFsx32k5',
            // ...
        ],
        'icon_url' => 'https://cdn.modrinth.com/data/Ck4E7v7R/04e8cbe7daff69feb20bf114074475001d9c7be5.png',
        'issues_url' => 'https://github.com/AppliedEnergistics/GuideME/issues',
        'source_url' => 'https://github.com/AppliedEnergistics/GuideME/',
        'wiki_url' => NULL,
        'discord_url' => 'https://discord.gg/Zd6t9ka7ne',
        'donation_urls' => [],
        'gallery' => [
            [
                'url' => 'https://cdn.modrinth.com/data/Ck4E7v7R/images/6e2fe4965e8ddc6a440ffa085d7a5df88fbd7424_350.webp',
                'raw_url' => 'https://cdn.modrinth.com/data/Ck4E7v7R/images/6e2fe4965e8ddc6a440ffa085d7a5df88fbd7424.png',
                'featured' => false,
                'title' => NULL,
                'description' => NULL,
                'created' => '2025-01-17T22:43:06.348280Z',
                'ordering' => 0,
            ],
        ],
        'color' => 8687273,
        'thread_id' => 'JwnifFEt',
        'monetization_status' => 'monetized',
    ];

    protected const VERSION_AE2_1 = [
        'game_versions' => [
            '1.21.1',
        ],
        'loaders' => [
            'neoforge',
        ],
        'id' => 'kfyIqgJ6',
        'project_id' => 'XxWD5pD3',
        'author_id' => '3HAu37Rl',
        'featured' => true,
        'name' => 'AE2 19.2.17 [NEOFORGE]',
        'version_number' => '19.2.17',
        'changelog' => '**Full Changelog**: https://github.com/AppliedEnergistics/Applied-Energistics-2/compare/neoforge/v19.2.16...neoforge/v19.2.17',
        'changelog_url' => NULL,
        'date_published' => '2025-09-23T23:32:37.609648Z',
        'downloads' => 113397,
        'version_type' => 'release',
        'status' => 'listed',
        'requested_status' => NULL,
        'files' => [
            [
                'id' => 'LIKKgXta',
                'hashes' =>
                    [
                        'sha512' => '55edfd948366aff620881e0625e48c333a2cb847e73249bc0b588efbc4b86709992a8ffbca97ea387e270df4186fe7f74ee2f27b739f1c952e932becfb9dea33',
                        'sha1' => '49c18d6a4af487957d7e5a6ad5dcbf71090b8e14',
                    ],
                'url' => 'https://cdn.modrinth.com/data/XxWD5pD3/versions/kfyIqgJ6/appliedenergistics2-19.2.17.jar',
                'filename' => 'appliedenergistics2-19.2.17.jar',
                'primary' => true,
                'size' => 8230896,
                'file_type' => NULL,
            ],
        ],
        'dependencies' => [
            [
                'version_id' => NULL,
                'project_id' => 'tq47Uqpn',
                'file_name' => NULL,
                'dependency_type' => 'incompatible',
            ],
            [
                'version_id' => NULL,
                'project_id' => 'Ck4E7v7R',
                'file_name' => NULL,
                'dependency_type' => 'required',
            ],
            [
                'version_id' => NULL,
                'project_id' => 'nfn13YXA',
                'file_name' => NULL,
                'dependency_type' => 'optional',
            ],
        ],
    ];
    protected const VERSION_AE2_2 = [
        'game_versions' => [
            '1.18.1',
        ],
        'loaders' => [
            'forge',
        ],
        'id' => 'OqkivEmV',
        'project_id' => 'XxWD5pD3',
        'author_id' => '3HAu37Rl',
        'featured' => false,
        'name' => '10.0.0-alpha.6 [FORGE]',
        'version_number' => 'forge-10.0.0-alpha.6',
        'changelog' => '## What\'s Changed * Reworked how fluid amounts are being entered in various dialogs (crafting amount, stocking amount, level emitter). They now allow entering the fluid amount in buckets with support for decimals (i.e. 0.25 B). (#5712)',
        'changelog_url' => NULL,
        'date_published' => '2021-12-14T17:05:15.319480Z',
        'downloads' => 276,
        'version_type' => 'alpha',
        'status' => 'listed',
        'requested_status' => NULL,
        'files' => [
            [
                'id' => '80UN0LrR',
                'hashes' =>
                    [
                        'sha512' => '5f9aa9535174bd0293e4e0a740bae900d20ee42ee51289ebf2da2f656d61a888f9e47d535acd29151716cb56abc70df251e8fb25fe507016d93ebec887768d62',
                        'sha1' => '5b557e22c04436a07c592363ef3fa8e207dda077',
                    ],
                'url' => 'https://cdn.modrinth.com/data/XxWD5pD3/versions/10.0.0-alpha.6/appliedenergistics2-10.0.0-alpha.6.jar',
                'filename' => 'appliedenergistics2-10.0.0-alpha.6.jar',
                'primary' => true,
                'size' => 4387130,
                'file_type' => NULL,
            ],
        ],
        'dependencies' => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->api = app(Modrinth::class);
    }

    public function test_search()
    {
        Http::fake(['https://api.modrinth.com/v2/search*' => Http::response([
            'hits' => [
                [
                    "project_id" => "P7dR8mSH",
                    "project_type" => "mod",
                    "slug" => "fabric-api",
                    "author" => "modmuss50",
                    "title" => "Fabric API",
                    "description" => "Lightweight and modular API providing common hooks and intercompatibility measures utilized by mods using the Fabric toolchain.",
                    "categories" => [
                        "fabric",
                        "library"
                    ],
                    "display_categories" => [
                        "fabric",
                        "library"
                    ],
                    "versions" => [
                        "1.21.9",
                        "1.21.10",
                        "1.21.11",
                    ],
                    "downloads" => 116973786,
                    "follows" => 26655,
                    "icon_url" => "https://cdn.modrinth.com/data/P7dR8mSH/icon.png",
                    "date_created" => "2021-01-22T11:04:41.419169Z",
                    "date_modified" => "2026-01-13T22:07:24.788363Z",
                    "latest_version" => "PXC4DUqw",
                    "license" => "Apache-2.0",
                    "client_side" => "optional",
                    "server_side" => "optional",
                    "gallery" => [],
                    "featured_gallery" => null,
                    "color" => 12367004
                ]
            ],
            'offset' => 0,
            'limit' => 10,
            'total_hits' => 99930
        ])]);

        $response = $this->api->search([]);

        Http::assertSentCount(1);

        $this->assertNotNull($response->getPagination());
        $this->assertSame(10, $response->getPagination()->perPage);
        $this->assertSame(1, $response->getPagination()->currentPage);
        $this->assertSame(99930 / 10, $response->getPagination()->lastPage);
        $this->assertSame(99930, $response->getPagination()->total);
        $this->assertSame(1, $response->getData()->count());

        $this->assertContainsOnlyInstancesOf(ProjectDTO::class, $response->getData());
    }

    public function test_get_project()
    {
        Http::fake(['https://api.modrinth.com/v2/project/ae2' => Http::response(self::PROJECT_AE2)]);

        $response = $this->api->getProject('ae2');
        /** @var ProjectDTO $project */
        $project = $response->getData();

        Http::assertSentCount(1);
        $this->assertNull($response->getPagination());
        $this->assertInstanceOf(ProjectDTO::class, $project);

        $this->assertSame(self::PROJECT_AE2['id'], $project->remoteId);
        $this->assertSame(self::PROJECT_AE2['title'], $project->name);
        $this->assertSame(self::PROJECT_AE2['description'], $project->summary);
        $this->assertSame(self::PROJECT_AE2['icon_url'], $project->logo);
        $this->assertSame(self::PROJECT_AE2['gallery'][0]['url'], $project->gallery[0]['thumbnail_url']);
        $this->assertSame(self::PROJECT_AE2['gallery'][0]['raw_url'], $project->gallery[0]['url']);
        $this->assertSame('https://modrinth.com/mod/ae2', $project->projectUrl);
        $this->assertSame(self::PROJECT_AE2['downloads'], $project->downloads);
        $this->assertEqualsCanonicalizing(self::PROJECT_AE2['loaders'], $project->loaders->map(fn(LoaderDTO $l) => $l->remoteId)->toArray());
        $this->assertSame(null, $project->authors);
        $this->assertEqualsCanonicalizing([EProjectType::MOD], $project->projectTypes->toArray());
        $this->assertEqualsCanonicalizing(self::PROJECT_AE2['categories'], $project->categories->map(fn(CategoryDTO $c) => $c->remoteId)->toArray());
    }

    public function test_get_projects()
    {
        Http::fake(['https://api.modrinth.com/v2/projects*' => Http::response([
            self::PROJECT_AE2,
            self::PROJECT_TINKERS_CONSTRUCT
        ])]);

        $response = $this->api->getProjects(['ae2', 'tinkers-construct']);

        Http::assertSentCount(1);
        $this->assertNull($response->getPagination());
        $this->assertSame(2, $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(ProjectDTO::class, $response->getData());
    }

    public function test_get_project_versions()
    {
        Http::fake(['https://api.modrinth.com/v2/project/ae2/version*' => Http::response([
            self::VERSION_AE2_1, self::VERSION_AE2_2
        ])]);

        $response = $this->api->getProjectVersions('ae2', []);

        Http::assertSentCount(1);
        $this->assertNull($response->getPagination());
        $this->assertSame(2, $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(VersionDTO::class, $response->getData());
    }

    public function test_get_project_dependencies()
    {
        Http::fake(['https://api.modrinth.com/v2/project/ae2/dependencies' => Http::response([
            'projects' => [
                self::PROJECT_GUIDEME
            ],
            'versions' => [
                // Not used...
                ['field that is deemed' => 'to fail']
            ]
        ])]);
        $response = $this->api->getProjectDependencies('ae2', []);

        Http::assertSentCount(1);
        $this->assertNull($response->getPagination());
        $this->assertSame(1, $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(ProjectDTO::class, $response->getData());
    }

    public function test_get_project_dependants()
    {
        $this->expectException(UnsupportedApiMethodException::class);
        $this->api->getProjectDependants('ae2', []);
    }

    public function test_get_version()
    {
        Http::fake(['https://api.modrinth.com/v2/version/'.self::VERSION_AE2_1['id'] => Http::response(self::VERSION_AE2_1)]);

        $response = $this->api->getVersion('ae2', self::VERSION_AE2_1['id']);
        /** @var VersionDTO $version */
        $version = $response->getData();

        Http::assertSentCount(1);
        $this->assertNull($response->getPagination());
        $this->assertInstanceOf(VersionDTO::class, $version);

        $this->assertSame(self::VERSION_AE2_1['id'], $version->remoteId);
        $this->assertSame(self::VERSION_AE2_1['name'], $version->name);
        $this->assertSame(self::VERSION_AE2_1['version_number'], $version->version);
        $this->assertSame(VersionType::RELEASE, $version->type);
        $this->assertNotSame(self::VERSION_AE2_1['changelog'], $version->changelog);
        $this->assertSame(self::VERSION_AE2_1['downloads'], $version->downloads);

        $this->assertContainsOnlyInstancesOf(FileDTO::class, $version->files);
        $this->assertSame(count(self::VERSION_AE2_1['files']), $version->files->count());
        $this->assertEqualsCanonicalizing(
            array_map(fn(array $f) => Arr::except($f, ['id']), self::VERSION_AE2_1['files']),
            $version->files->map(fn(FileDTO $f) => [
//                'id' => $f->remoteId,
                'hashes' => [
                    'sha1' => $f->hashes->get('sha1'),
                    'sha512' => $f->hashes->get('sha512')
                ],
                'url' => $f->url,
                'filename' => $f->name,
                'primary' => $f->primary,
                'size' => $f->size,
                'file_type' => null
            ])->toArray()
        );

        $this->assertEqualsCanonicalizing(self::VERSION_AE2_1['game_versions'], array_map(fn(array $gv) => $gv['remote_id'], $version->gameVersions));

        $this->assertContainsOnlyInstancesOf(LoaderDTO::class, $version->loaders);
        $this->assertEqualsCanonicalizing(self::VERSION_AE2_1['loaders'], $version->loaders->map(fn(LoaderDTO $l) => $l->remoteId)->toArray());

        $this->assertContainsOnlyInstancesOf(DependencyDTO::class, $version->dependencies);
        $this->assertEqualsCanonicalizing(self::VERSION_AE2_1['dependencies'], $version->dependencies->map(fn(DependencyDTO $d) => [
            'version_id' => $d->versionId,
            'project_id' => $d->projectId,
            'file_name' => $d->filename,
            'dependency_type' => $d->type
        ])->toArray());

        $this->assertTrue(Carbon::make(self::VERSION_AE2_1['date_published'])->equalTo($version->publishedAt));
    }

    public function test_get_versions()
    {
        Http::fake(['https://api.modrinth.com/v2/versions*' => Http::response([
            self::VERSION_AE2_1, self::VERSION_AE2_2
        ])]);

        $response = $this->api->getVersions([self::VERSION_AE2_1['id'], self::VERSION_AE2_2['id']]);

        Http::assertSentCount(1);
        $this->assertNull($response->getPagination());
        $this->assertSame(2, $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(VersionDTO::class, $response->getData());
        $this->assertNotNull($response->getData()->first(fn(VersionDTO $v) => $v->remoteId === self::VERSION_AE2_1['id']));
        $this->assertNotNull($response->getData()->first(fn(VersionDTO $v) => $v->remoteId === self::VERSION_AE2_2['id']));
    }

    public function test_get_versions_from_hashes()
    {
        $algo = 'sha512';
        $hash = self::VERSION_AE2_1['files'][0]['hashes'][$algo];

        Http::fake(['https://api.modrinth.com/v2/version_files' => Http::response([
            $hash => self::VERSION_AE2_1
        ])]);

        $response = $this->api->getVersionsFromHashes([$hash], $algo);

        Http::assertSentCount(1);
        $this->assertNull($response->getPagination());
        $this->assertSame(1, $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(VersionDTO::class, $response->getData());
        $this->assertArrayHasKey($hash, $response->getData());
        $this->assertNotNull($response->getData()->first(fn(VersionDTO $v) => $v->remoteId === self::VERSION_AE2_1['id']));

        $this->expectException(UnsupportedApiMethodException::class);
        $this->api->getVersionsFromHashes([Str::random(32)], 'md5');
    }

    public function test_get_version_files()
    {
        Http::fake(
            ['https://api.modrinth.com/v2/version/'.self::VERSION_AE2_1['id'] => Http::response(self::VERSION_AE2_1)]
        );

        $response = $this->api->getVersionFiles('ae2', self::VERSION_AE2_1['id']);
        $files = self::VERSION_AE2_1['files'];

        Http::assertSentCount(1);
        $this->assertNull($response->getPagination());
        $this->assertSame(count($files), $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(FileDTO::class, $response->getData());
        $this->assertNotNull($file = $response->getData()->first(fn(FileDTO $f) => $f->remoteId === $files[0]['filename']));
        $this->assertSame($files[0]['url'], $file->url);
        $this->assertSame(true, $file->primary);
    }

    public function test_get_version_dependencies()
    {
        Http::fake([
            'https://api.modrinth.com/v2/version/'.self::VERSION_AE2_1['id'] => Http::response(self::VERSION_AE2_1),
            'https://api.modrinth.com/v2/projects*' => Http::response([
                self::PROJECT_GUIDEME
            ]),
        ]);

        $response = $this->api->getVersionDependencies('ae2', self::VERSION_AE2_1['id']);

        Http::assertSentCount(2);
        $this->assertNull($response->getPagination());
        $this->assertSame(1, $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(ProjectDTO::class, $response->getData());
        /** @var ProjectDTO $project */
        $this->assertNotNull($project = $response->getData()->first(fn(ProjectDTO $p) => $p->remoteId === self::PROJECT_GUIDEME['id']));
        $this->assertSame(ProjectDependencyType::REQUIRED, $project->dependencyType);
    }

    public function test_get_version_dependants()
    {
        $this->expectException(UnsupportedApiMethodException::class);
        $this->api->getVersionDependants('ae2', self::VERSION_AE2_1['id']);
    }

    public function test_get_project_authors()
    {
        Http::fake(['https://api.modrinth.com/v2/project/ae2/members' => Http::response([
            [
                'role' => 'Owner',
                'team_id' => 'CqWdb4w5',
                'user' => [
                    'id' => '3HAu37Rl',
                    'username' => 'shartte',
                    'avatar_url' => 'https://avatars.githubusercontent.com/u/1261399?v=4',
                    'bio' => NULL,
                    'created' => '2021-12-14T11:46:56.103698Z',
                    'role' => 'developer',
                    'badges' => 0,
                    'auth_providers' => NULL,
                    'email' => NULL,
                    'email_verified' => NULL,
                    'has_password' => NULL,
                    'has_totp' => NULL,
                    'payout_data' => NULL,
                    'stripe_customer_id' => NULL,
                    'allow_friend_requests' => NULL,
                    'github_id' => NULL,
                ],
                'permissions' => NULL,
                'accepted' => true,
                'payouts_split' => NULL,
                'ordering' => 0,
            ]
        ])]);

        $response = $this->api->getProjectAuthors('ae2');

        Http::assertSentCount(1);
        $this->assertNull($response->getPagination());

        $this->assertSame(1, $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(AuthorDTO::class, $response->getData());
        $this->assertNotNull($author = $response->getData()->first(fn(AuthorDTO $a) => $a->remoteId === '3HAu37Rl'));
        $this->assertSame('Owner', $author->role);
    }

    public function test_get_categories()
    {
        Http::fake(['https://api.modrinth.com/v2/tag/category' => Http::response([
            [
                'icon' => '<svg></svg>',
                'name' => 'adventure',
                'project_type' => 'modpack',
                'header' => 'categories',
            ],
            [
                'icon' => '<svg></svg>',
                'name' => 'adventure',
                'project_type' => 'mod',
                'header' => 'categories',
            ],
            [
                'icon' => '<svg></svg>',
                'name' => 'vanilla-like',
                'project_type' => 'resourcepack',
                'header' => 'categories'
            ]
        ])]);

        $response = $this->api->getCategories();

        Http::assertSentCount(1);
        $this->assertSame(2, $response->count());
        $this->assertContainsOnlyInstancesOf(CategoryDTO::class, $response);
        // Uppercase name
        $this->assertNotNull($adventure = $response->first(fn(CategoryDTO $c) => $c->name === 'Adventure'));
        // Special case name
        $this->assertNotNull($vanilla = $response->first(fn(CategoryDTO $c) => $c->name === 'Vanilla-like'));
        $this->assertEqualsCanonicalizing(
            [EProjectType::MOD, EProjectType::MODPACK, EProjectType::PLUGIN, EProjectType::DATAPACK],
            $adventure->projectTypes->toArray()
        );
        $this->assertEqualsCanonicalizing([EProjectType::RESOURCE_PACK], $vanilla->projectTypes->toArray());
    }

    public function test_get_loaders()
    {
        Http::fake(['https://api.modrinth.com/v2/tag/loader' => Http::response([
            [
                'icon' => '<svg></svg>',
                'name' => 'fabric',
                'supported_project_types' => [
                    'mod',
                    'project',
                    'modpack',
                ]
            ],
            [
                'icon' => '<svg></svg>',
                'name' => 'forge',
                'supported_project_types' => [
                    'mod',
                    'project',
                    'modpack',
                ]
            ]
        ])]);

        $response = $this->api->getLoaders();

        Http::assertSentCount(1);
        $this->assertSame(2, $response->count());
        $this->assertContainsOnlyInstancesOf(LoaderDTO::class, $response);
        $this->assertNotNull($fabric = $response->first(fn(LoaderDTO $l) => $l->name === 'Fabric'));
        $this->assertNotNull($forge = $response->first(fn(LoaderDTO $l) => $l->name === 'Forge'));
        $this->assertEqualsCanonicalizing([EProjectType::MOD, EProjectType::MODPACK], $fabric->projectTypes->toArray());
        $this->assertEqualsCanonicalizing([EProjectType::MOD, EProjectType::MODPACK], $forge->projectTypes->toArray());
    }
}
