<?php

namespace Tests\Unit\Platforms;

use App\API\Contracts\ThirdPartyApiTest;
use App\API\Platform\Curseforge;
use App\API\DTO\AuthorDTO;
use App\API\DTO\CategoryDTO;
use App\API\DTO\DependencyDTO;
use App\API\DTO\FileDTO;
use App\API\DTO\LoaderDTO;
use App\API\DTO\ProjectDTO;
use App\API\DTO\VersionDTO;
use App\Enums\EProjectType;
use App\Enums\ProjectDependencyType;
use App\Enums\VersionType;
use App\Exceptions\UnsupportedApiMethodException;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurseforgeTest extends TestCase implements ThirdPartyApiTest
{
    protected Curseforge $api;

    protected const PROJECT_AE2 = [
        'id' => 223794,
        'gameId' => 432,
        'name' => 'Applied Energistics 2',
        'slug' => 'applied-energistics-2',
        'links' => [
            'websiteUrl' => 'https://www.curseforge.com/minecraft/mc-mods/applied-energistics-2',
            'wikiUrl' => 'https://guide.appliedenergistics.org/',
            'issuesUrl' => 'https://github.com/AppliedEnergistics/Applied-Energistics-2/issues',
            'sourceUrl' => 'https://github.com/AppliedEnergistics/Applied-Energistics-2',
        ],
        'summary' => 'A Mod about Matter, Energy and using them to conquer the world..',
        'status' => 4,
        'downloadCount' => 201427106,
        'isFeatured' => false,
        'primaryCategoryId' => 415,
        'categories' => [
            [
                'id' => 415,
                'gameId' => 432,
                'name' => 'Energy, Fluid, and Item Transport',
                'slug' => 'technology-item-fluid-energy-transport',
                'url' => 'https://www.curseforge.com/minecraft/mc-mods/technology/technology-item-fluid-energy-transport',
                'iconUrl' => 'https://mods.net/a/547291.png',
                'dateModified' => '2014-05-08T17:39:07.873Z',
                'isClass' => false,
                'classId' => 6,
                'parentCategoryId' => 412,
            ],
            [
                'id' => 434,
                'gameId' => 432,
                'name' => 'Armor, Tools, and Weapons',
                'slug' => 'armor-weapons-tools',
                'url' => 'https://www.curseforge.com/minecraft/mc-mods/armor-weapons-tools',
                'iconUrl' => 'https://mods.net/a/87958.png',
                'dateModified' => '2014-05-08T17:44:39.057Z',
                'isClass' => false,
                'classId' => 6,
                'parentCategoryId' => 6,
            ],
            [
                'id' => 408,
                'gameId' => 432,
                'name' => 'Ores and Resources',
                'slug' => 'world-ores-resources',
                'url' => 'https://www.curseforge.com/minecraft/mc-mods/world-gen/world-ores-resources',
                'iconUrl' => 'https://mods.net/a/66510.png',
                'dateModified' => '2014-05-08T17:36:41.233Z',
                'isClass' => false,
                'classId' => 6,
                'parentCategoryId' => 406,
            ],
            [
                'id' => 412,
                'gameId' => 432,
                'name' => 'Technology',
                'slug' => 'technology',
                'url' => 'https://www.curseforge.com/minecraft/mc-mods/technology',
                'iconUrl' => 'https://mods.net/a/945939.png',
                'dateModified' => '2014-05-08T17:37:54.597Z',
                'isClass' => false,
                'classId' => 6,
                'parentCategoryId' => 6,
            ],
            [
                'id' => 420,
                'gameId' => 432,
                'name' => 'Storage',
                'slug' => 'storage',
                'url' => 'https://www.curseforge.com/minecraft/mc-mods/storage',
                'iconUrl' => 'https://mods.net/a/61401.png',
                'dateModified' => '2014-05-08T17:41:17.203Z',
                'isClass' => false,
                'classId' => 6,
                'parentCategoryId' => 6,
            ],
        ],
        'classId' => 6,
        'authors' => [
            [
                'id' => 101370726,
                'name' => 'thetechnici4n',
                'url' => 'https://www.curseforge.com/members/thetechnici4n',
                'avatarUrl' => 'https://static-cdn.jtvnw.net/user-default-pictures-uv/13e5fa74-defa-11e9-809c-784f43822e80-profile_image-150x150.png',
            ],
            [
                'id' => 101333983,
                'name' => 'shartte',
                'url' => 'https://www.curseforge.com/members/shartte',
                'avatarUrl' => 'https://static-cdn.jtvnw.net/user-default-pictures-uv/75305d54-c7cc-40d1-bb9c-91fbe85943c7-profile_image-150x150.png',
            ],
            [
                'id' => 16673090,
                'name' => 'TeamAppliedEnergistics',
                'url' => 'https://www.curseforge.com/members/teamappliedenergistics',
                'avatarUrl' => 'https://static-cdn.jtvnw.net/user-default-pictures-uv/de130ab0-def7-11e9-b668-784f43822e80-profile_image-150x150.png',
            ],
        ],
        'logo' => [
            'id' => 1025127,
            'modId' => 223794,
            'title' => '638548475358792693.webp',
            'description' => '',
            'thumbnailUrl' => '',
            'url' => 'https://mods.net/a/6385693.webp',
        ],
        'screenshots' => [
            [
                'description' => 'Cool example here',
                'id' => 12345,
                'modId' => 223794,
                'thumbnailUrl' => 'https://mods.net/a/t/12345.png',
                'title' => 'Screenshot',
                'url' => 'https://mods.net/a/12345.png',
            ]
        ],
        'mainFileId' => 7148494,
        'latestFiles' => [
            [
                'id' => 6014947,
                'gameId' => 432,
                'modId' => 223794,
                'isAvailable' => true,
                'displayName' => 'AE2 15.3.2-beta [NEOFORGE]',
                'fileName' => 'appliedenergistics2-forge-15.3.2-beta.jar',
                'releaseType' => 2,
                'fileStatus' => 4,
                'hashes' => [
                    [
                        'value' => '32e8e3f67229b0433c3779e09bd7a2c9e3a8fb84',
                        'algo' => 1,
                    ],
                    [
                        'value' => 'dae718d3715b4059d7eba1401418ec58',
                        'algo' => 2,
                    ],
                ],
                'fileDate' => '2024-12-22T23:20:40.817Z',
                'fileLength' => 9751697,
                'downloadCount' => 314188,
                'fileSizeOnDisk' => 17528162,
                'downloadUrl' => 'https://mods.net/appliedenergistics2-forge-15.3.2-beta.jar',
                'gameVersions' => [
                    'NeoForge',
                    '1.20.1',
                    'Forge',
                ],
                'sortableGameVersions' => [
                    [
                        'gameVersionName' => 'NeoForge',
                        'gameVersionPadded' => '0',
                        'gameVersion' => '',
                        'gameVersionReleaseDate' => '2023-07-25T00:00:00Z',
                        'gameVersionTypeId' => 68441,
                    ],
                    [
                        'gameVersionName' => '1.20.1',
                        'gameVersionPadded' => '0000000001.0000000020.0000000001',
                        'gameVersion' => '1.20.1',
                        'gameVersionReleaseDate' => '2023-06-12T14:26:38.477Z',
                        'gameVersionTypeId' => 75125,
                    ],
                    [
                        'gameVersionName' => 'Forge',
                        'gameVersionPadded' => '0',
                        'gameVersion' => '',
                        'gameVersionReleaseDate' => '2022-10-01T00:00:00Z',
                        'gameVersionTypeId' => 68441,
                    ],
                ],
                'dependencies' => [
                    [
                        'modId' => 1173950,
                        'relationType' => 3,
                    ],
                    [
                        'modId' => 310111,
                        'relationType' => 2,
                    ],
                    [
                        'modId' => 238222,
                        'relationType' => 2,
                    ],
                    [
                        'modId' => 245211,
                        'relationType' => 2,
                    ],
                    [
                        'modId' => 324717,
                        'relationType' => 2,
                    ],
                    [
                        'modId' => 440979,
                        'relationType' => 2,
                    ],
                ],
                'alternateFileId' => 0,
                'isServerPack' => false,
                'fileFingerprint' => 3393911614,
                'modules' => [
                    // cut...
                ]
            ],
            [
                'id' => 7148494,
                'gameId' => 432,
                'modId' => 223794,
                'isAvailable' => true,
                'displayName' => 'AE2 15.4.10 [FABRIC]',
                'fileName' => 'appliedenergistics2-fabric-15.4.10.jar',
                'releaseType' => 1,
                'fileStatus' => 4,
                'hashes' => [
                    [
                        'value' => 'c8e746a11c4a658457b61e54d7da9f749b3ae03c',
                        'algo' => 1,
                    ],
                    [
                        'value' => '132168683f2a1bb063b708a77ee97a4d',
                        'algo' => 2,
                    ],
                ],
                'fileDate' => '2025-10-25T14:54:42.127Z',
                'fileLength' => 9824629,
                'downloadCount' => 227675,
                'fileSizeOnDisk' => 17406069,
                'downloadUrl' => 'https://mods.net/appliedenergistics2-fabric-15.4.10.jar',
                'gameVersions' => [
                    'Fabric',
                    '1.20.1',
                ],
                'sortableGameVersions' => [
                    [
                        'gameVersionName' => 'Fabric',
                        'gameVersionPadded' => '0',
                        'gameVersion' => '',
                        'gameVersionReleaseDate' => '2022-09-01T00:00:00Z',
                        'gameVersionTypeId' => 68441,
                    ],
                    [
                        'gameVersionName' => '1.20.1',
                        'gameVersionPadded' => '0000000001.0000000020.0000000001',
                        'gameVersion' => '1.20.1',
                        'gameVersionReleaseDate' => '2023-06-12T14:26:38.477Z',
                        'gameVersionTypeId' => 75125,
                    ],
                ],
                'dependencies' => [
                    [
                        'modId' => 440979,
                        'relationType' => 2,
                    ],
                    [
                        'modId' => 310111,
                        'relationType' => 2,
                    ],
                    [
                        'modId' => 238222,
                        'relationType' => 2,
                    ],
                    [
                        'modId' => 324717,
                        'relationType' => 2,
                    ],
                ],
                'alternateFileId' => 0,
                'isServerPack' => false,
                'fileFingerprint' => 857648470,
                'modules' => [
                    // cut..
                ]
            ],
        ],
        'latestFilesIndexes' => [
            // cut..
        ],
        'latestEarlyAccessFilesIndexes' => [],
        'dateCreated' => '2014-08-27T20:04:28.857Z',
        'dateModified' => '2025-11-01T14:55:50.533Z',
        'dateReleased' => '2025-11-01T14:52:21.387Z',
        'allowModDistribution' => true,
        'gamePopularityRank' => 123,
        'isAvailable' => true,
        'hasCommentsEnabled' => false,
        'thumbsUpCount' => 0,
        'featuredProjectTag' => 0,
    ];
    protected const PROJECT_GUIDEME = [
        'screenshots' => [],
        'id' => 1173950,
        'gameId' => 432,
        'name' => 'GuideME',
        'slug' => 'guideme',
        'links' => [
            'websiteUrl' => 'https://www.curseforge.com/minecraft/mc-mods/guideme',
            'wikiUrl' => 'https://guideme.appliedenergistics.org',
            'issuesUrl' => 'https://github.com/AppliedEnergistics/GuideME/issues',
            'sourceUrl' => 'https://github.com/AppliedEnergistics/GuideME',
        ],
        'summary' => 'A guidebook toolkit for mods and modpack makers alike with comfortable markdown formatting, and live 3d scenes!',
        'status' => 4,
        'downloadCount' => 19122166,
        'isFeatured' => false,
        'primaryCategoryId' => 5191,
        'categories' => [
            [
                'id' => 4545,
                'gameId' => 432,
                'name' => 'Applied Energistics 2',
                'slug' => 'applied-energistics-2',
                'url' => 'https://www.curseforge.com/minecraft/mc-mods/mc-addons/applied-energistics-2',
                'iconUrl' => 'https://mods.net/a/8549101.png',
                'dateModified' => '2015-09-14T14:18:48.563Z',
                'isClass' => false,
                'classId' => 6,
                'parentCategoryId' => 426,
            ],
            [
                'id' => 5191,
                'gameId' => 432,
                'name' => 'Utility & QoL',
                'slug' => 'utility-qol',
                'url' => 'https://www.curseforge.com/minecraft/mc-mods/utility-qol',
                'iconUrl' => 'https://mods.net/a/7908633.png',
                'dateModified' => '2021-11-17T11:45:09.143Z',
                'isClass' => false,
                'classId' => 6,
                'parentCategoryId' => 6,
            ],
            [
                'id' => 423,
                'gameId' => 432,
                'name' => 'Map and Information',
                'slug' => 'map-information',
                'url' => 'https://www.curseforge.com/minecraft/mc-mods/map-information',
                'iconUrl' => 'https://mods.net/a/537438.png',
                'dateModified' => '2014-05-08T17:42:23.74Z',
                'isClass' => false,
                'classId' => 6,
                'parentCategoryId' => 6,
            ],
            [
                'id' => 421,
                'gameId' => 432,
                'name' => 'API and Library',
                'slug' => 'library-api',
                'url' => 'https://www.curseforge.com/minecraft/mc-mods/library-api',
                'iconUrl' => 'https://mods.net/a/49676.png',
                'dateModified' => '2014-05-23T03:21:44.06Z',
                'isClass' => false,
                'classId' => 6,
                'parentCategoryId' => 6,
            ],
        ],
        'classId' => 6,
        'authors' => [
            [
                'id' => 101333983,
                'name' => 'shartte',
                'url' => 'https://www.curseforge.com/members/shartte',
                'avatarUrl' => 'https://static-cdn.jtvnw.net/user-default-pictures-uv/75305d54-c7cc-40d1-bb9c-91fbe85943c7-profile_image-150x150.png',
            ],
        ],
        'logo' => [
            'id' => 1151155,
            'modId' => 1173950,
            'title' => '638718882756139313.png',
            'description' => '',
            'thumbnailUrl' => 'https://mods.net/a/t/71313.png',
            'url' => 'https://mods.net/a/82756.png',
        ],
        'mainFileId' => 7127448,
        'latestFiles' => [
            [
                'id' => 7127447,
                'gameId' => 432,
                'modId' => 1173950,
                'isAvailable' => true,
                'displayName' => 'GuideME 20.1.14',
                'fileName' => 'guideme-20.1.14.jar',
                'releaseType' => 1,
                'fileStatus' => 4,
                'hashes' => [
                    [
                        'value' => '57d883148f04989128505a1bd8919629440f714f',
                        'algo' => 1,
                    ],
                    [
                        'value' => '6425ddca1593d7f73c7160e54333cfef',
                        'algo' => 2,
                    ],
                ],
                'fileDate' => '2025-10-19T22:32:04.637Z',
                'fileLength' => 9413204,
                'downloadCount' => 0,
                'fileSizeOnDisk' => 20045677,
                'downloadUrl' => 'https://mods.net/guideme-20.1.14.jar',
                'gameVersions' => [
                    '1.20.1',
                    'Forge',
                ],
                'sortableGameVersions' => [
                    [
                        'gameVersionName' => '1.20.1',
                        'gameVersionPadded' => '0000000001.0000000020.0000000001',
                        'gameVersion' => '1.20.1',
                        'gameVersionReleaseDate' => '2023-06-12T14:26:38.477Z',
                        'gameVersionTypeId' => 75125,
                    ],
                    [
                        'gameVersionName' => 'Forge',
                        'gameVersionPadded' => '0',
                        'gameVersion' => '',
                        'gameVersionReleaseDate' => '2022-10-01T00:00:00Z',
                        'gameVersionTypeId' => 68441,
                    ],
                ],
                'dependencies' => [],
                'alternateFileId' => 0,
                'isServerPack' => false,
                'fileFingerprint' => 768472251,
                'modules' => [
                    // cut..
                ]
            ],
            [
                'id' => 7127448,
                'gameId' => 432,
                'modId' => 1173950,
                'isAvailable' => true,
                'displayName' => 'GuideME 20.4.6',
                'fileName' => 'guideme-20.4.6.jar',
                'releaseType' => 1,
                'fileStatus' => 4,
                'hashes' => [
                    [
                        'value' => 'de3c01a2e5d1079389d37c7a582131634c59dfb8',
                        'algo' => 1,
                    ],
                    [
                        'value' => 'f3d9f14a06796f6ffd4704f84fa5f009',
                        'algo' => 2,
                    ],
                ],
                'fileDate' => '2025-10-19T22:32:11.137Z',
                'fileLength' => 9300833,
                'downloadCount' => 1618,
                'fileSizeOnDisk' => 20008938,
                'downloadUrl' => 'https://mods.net/guideme-20.4.6.jar',
                'gameVersions' => [
                    '1.20.4',
                    'NeoForge',
                ],
                'sortableGameVersions' => [
                    [
                        'gameVersionName' => '1.20.4',
                        'gameVersionPadded' => '0000000001.0000000020.0000000004',
                        'gameVersion' => '1.20.4',
                        'gameVersionReleaseDate' => '2023-12-07T15:17:47.907Z',
                        'gameVersionTypeId' => 75125,
                    ],
                    [
                        'gameVersionName' => 'NeoForge',
                        'gameVersionPadded' => '0',
                        'gameVersion' => '',
                        'gameVersionReleaseDate' => '2023-07-25T00:00:00Z',
                        'gameVersionTypeId' => 68441,
                    ],
                ],
                'dependencies' => [],
                'alternateFileId' => 0,
                'isServerPack' => false,
                'fileFingerprint' => 3699833169,
                'modules' => [
                    // cut..
                ]
            ],
        ],
        'latestFilesIndexes' => [
            [
                'gameVersion' => '1.20.1',
                'fileId' => 7127447,
                'filename' => 'guideme-20.1.14.jar',
                'releaseType' => 1,
                'gameVersionTypeId' => 75125,
                'modLoader' => 1,
            ],
            [
                'gameVersion' => '1.21.1',
                'fileId' => 7127444,
                'filename' => 'guideme-21.1.15.jar',
                'releaseType' => 1,
                'gameVersionTypeId' => 77784,
                'modLoader' => 6,
            ],
        ],
        'latestEarlyAccessFilesIndexes' => [],
        'dateCreated' => '2025-01-19T14:27:26.523Z',
        'dateModified' => '2025-11-01T15:44:18.573Z',
        'dateReleased' => '2025-11-01T15:41:29.577Z',
        'allowModDistribution' => true,
        'gamePopularityRank' => 222,
        'isAvailable' => true,
        'hasCommentsEnabled' => false,
        'thumbsUpCount' => 0,
        'socialLinks' => [
            // cut..
        ],
        'featuredProjectTag' => 0,
    ];

    protected const VERSION_AE2_1 = self::PROJECT_AE2['latestFiles'][0];
    protected const VERSION_AE2_2 = self::PROJECT_AE2['latestFiles'][1];

    protected const CATEGORIES = [
        [
            'id' => 395,
            'gameId' => 432,
            'name' => '64x',
            'slug' => 'sixty-four-x',
            'url' => 'https://www.curseforge.com/minecraft/texture-packs/sixty-four-x',
            'iconUrl' => 'https://mods.net/a/322612.png',
            'dateModified' => '2020-05-08T17:54:41.233Z',
            'isClass' => false,
            'classId' => 12,
            'parentCategoryId' => 12,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->useAppSettings()->setSettings(['platforms.curseforge.api_key' => 'THE-API-KEY']);
        $this->api = app(Curseforge::class);
    }

    public function test_search()
    {
        Http::fake(['https://api.curseforge.com/v1/mods/search*' => Http::response([
            'data' => [
                self::PROJECT_AE2
            ],
            'pagination' => [
                'index' => 0,
                'pageSize' => 50,
                'resultCount' => 50,
                'totalCount' => 250000,
            ]
        ])]);

        $response = $this->api->search([]);

        Http::assertSentCount(1);

        $this->assertNotNull($response->getPagination());
        $this->assertSame(50, $response->getPagination()->perPage);
        $this->assertSame(1, $response->getPagination()->currentPage);
        $this->assertSame(250000 / 50, $response->getPagination()->lastPage);
        $this->assertSame(250000, $response->getPagination()->total);
        $this->assertSame(1, $response->getData()->count());

        $this->assertSame(1, $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(ProjectDTO::class, $response->getData());
    }

    public function test_get_project()
    {
        Http::fake([
            'https://api.curseforge.com/v1/mods/'.self::PROJECT_AE2['id'] => Http::response([
                'data' => self::PROJECT_AE2
            ]),
            'https://api.curseforge.com/v1/mods/'.self::PROJECT_AE2['id'].'/description' => Http::response([
                'data' => '<p>Applied Energistics 2 (AE2) introduces a unique approach to inventory management and automation.</p>'
            ]),
        ]);

        $response = $this->api->getProject(self::PROJECT_AE2['id']);
        /** @var ProjectDTO $project */
        $project = $response->getData();

        Http::assertSentCount(2);
        $this->assertNull($response->getPagination());
        $this->assertInstanceOf(ProjectDTO::class, $project);

        $this->assertSame((string)self::PROJECT_AE2['id'], $project->remoteId);
        $this->assertSame(self::PROJECT_AE2['name'], $project->name);
        $this->assertSame(self::PROJECT_AE2['summary'], $project->summary);
        $this->assertSame(self::PROJECT_AE2['logo']['url'], $project->logo);
        $this->assertSame(self::PROJECT_AE2['links']['websiteUrl'], $project->projectUrl);
        $this->assertSame(self::PROJECT_AE2['downloadCount'], $project->downloads);

        $this->assertNotEmpty($project->gallery);
        $this->assertSame(self::PROJECT_AE2['screenshots'][0]['title'], $project->gallery[0]['title']);
        $this->assertSame(self::PROJECT_AE2['screenshots'][0]['description'], $project->gallery[0]['description']);
        $this->assertSame(self::PROJECT_AE2['screenshots'][0]['thumbnailUrl'], $project->gallery[0]['thumbnail_url']);
        $this->assertSame(self::PROJECT_AE2['screenshots'][0]['url'], $project->gallery[0]['url']);

        $this->assertEqualsCanonicalizing(null, $project->loaders);

        $this->assertContainsOnlyInstancesOf(AuthorDTO::class, $project->authors);
        $this->assertEqualsCanonicalizing(self::PROJECT_AE2['authors'], $project->authors->map(fn(AuthorDTO $a) => [
            'id' => $a->remoteId,
            'name' => $a->name,
            'url' => $a->url,
            'avatarUrl' => $a->avatarUrl
        ])->toArray());

        $this->assertEqualsCanonicalizing([EProjectType::MOD], $project->projectTypes->toArray());

        $this->assertContainsOnlyInstancesOf(CategoryDTO::class, $project->categories);
        $this->assertEqualsCanonicalizing(
            Arr::pluck(self::PROJECT_AE2['categories'], 'id'),
            $project->categories->map(fn(CategoryDTO $c) => $c->remoteId)->toArray()
        );
    }

    public function test_get_projects()
    {
        Http::fake(['https://api.curseforge.com/v1/mods' => Http::response([
            'data' => [self::PROJECT_AE2]
        ])]);

        $response = $this->api->getProjects([self::PROJECT_AE2['id']]);

        Http::assertSentCount(1);
        $this->assertNull($response->getPagination());
        $this->assertSame(1, $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(ProjectDTO::class, $response->getData());
    }

    public function test_get_project_versions()
    {
        Http::fake(['https://api.curseforge.com/v1/mods/'.self::PROJECT_AE2['id'].'/files' => Http::response([
            'data' => [self::VERSION_AE2_1, self::VERSION_AE2_2],
            'pagination' => [
                'index' => 0,
                'pageSize' => 50,
                'resultCount' => 50,
                'totalCount' => 500,
            ]
        ])]);

        $response = $this->api->getProjectVersions(self::PROJECT_AE2['id'], []);

        Http::assertSentCount(1);
        $this->assertNotNull($response->getPagination());
        $this->assertSame(2, $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(VersionDTO::class, $response->getData());
    }

    public function test_get_project_dependencies()
    {
        $this->expectException(UnsupportedApiMethodException::class);
        $this->api->getProjectDependencies(123);
    }

    public function test_get_project_dependants()
    {
        $this->expectException(UnsupportedApiMethodException::class);
        $this->api->getProjectDependants(123);
    }

    public function test_get_version()
    {
        Http::fake([
            sprintf('https://api.curseforge.com/v1/mods/%s/files/%s', self::PROJECT_AE2['id'], self::VERSION_AE2_1['id'])
                => Http::response(['data' => self::VERSION_AE2_1]),
            sprintf('https://api.curseforge.com/v1/mods/%s/files/%s/changelog', self::PROJECT_AE2['id'], self::VERSION_AE2_1['id'])
                => Http::response(['data' => '<p>Full Changelog: https://github.com/AppliedEnergistics/Applied-Energistics-2/compare/...</p>'])
        ]);

        $response = $this->api->getVersion(self::PROJECT_AE2['id'], self::VERSION_AE2_1['id']);
        /** @var VersionDTO $version */
        $version = $response->getData();

        Http::assertSentCount(2);
        $this->assertNull($response->getPagination());
        $this->assertInstanceOf(VersionDTO::class, $version);

        $this->assertSame((string)self::VERSION_AE2_1['id'], $version->remoteId);
        $this->assertSame(self::VERSION_AE2_1['displayName'], $version->name);
        $this->assertSame(null, $version->version);
        $this->assertSame(VersionType::BETA, $version->type);
        $this->assertNotNull($version->changelog);
        $this->assertSame(self::VERSION_AE2_1['downloadCount'], $version->downloads);
        $this->assertTrue(Carbon::make(self::VERSION_AE2_1['fileDate'])->equalTo($version->publishedAt));

        $this->assertContainsOnlyInstancesOf(FileDTO::class, $version->files);
        $this->assertSame(1, $version->files->count());
        /** @var FileDTO $file */
        $file = $version->files->first();
        $this->assertSame((string)self::VERSION_AE2_1['id'], $file->remoteId);
        $this->assertSame(self::VERSION_AE2_1['fileName'], $file->name);
        $this->assertSame(self::VERSION_AE2_1['downloadUrl'], $file->url);
        $this->assertSame(self::VERSION_AE2_1['fileLength'], $file->size);
//        $this->assertSame(self::VERSION_AE2_1[''], $file->hashes);
        $this->assertSame(true, $file->primary);

        $this->assertEqualsCanonicalizing(['1.20.1'], array_map(fn(array $gv) => $gv['remote_id'], $version->gameVersions));

        $this->assertContainsOnlyInstancesOf(LoaderDTO::class, $version->loaders);
        $this->assertEqualsCanonicalizing(['Forge', 'NeoForge'], $version->loaders->map(fn(LoaderDTO $l) => $l->remoteId)->toArray());

        $this->assertContainsOnlyInstancesOf(DependencyDTO::class, $version->dependencies);
        $this->assertEqualsCanonicalizing(
            Arr::pluck(self::VERSION_AE2_1['dependencies'], 'modId'),
            $version->dependencies->map(fn(DependencyDTO $d) => $d->projectId)->toArray()
        );
    }

    public function test_get_versions()
    {
        Http::fake(['https://api.curseforge.com/v1/mods/files' => Http::response([
            'data' => [self::VERSION_AE2_1, self::VERSION_AE2_2]
        ])]);

        $response = $this->api->getVersions([self::VERSION_AE2_1['id'], self::VERSION_AE2_2['id']]);

        Http::assertSentCount(1);
        $this->assertNull($response->getPagination());
        $this->assertSame(2, $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(VersionDTO::class, $response->getData());
        $this->assertNotNull($version1 = $response->getData()->first(
            fn(VersionDTO $v) => $v->remoteId === (string)self::VERSION_AE2_1['id']
        ));
        $this->assertNotNull($version2 = $response->getData()->first(
            fn(VersionDTO $v) => $v->remoteId === (string)self::VERSION_AE2_2['id']
        ));
        $this->assertTrue($version1->files->first()->primary);
        $this->assertTrue($version2->files->first()->primary);
    }

    public function test_get_versions_from_hashes()
    {
        $this->expectException(UnsupportedApiMethodException::class);
        $this->api->getVersionsFromHashes(['foobar'], 'sha1');
    }

    public function test_get_version_files()
    {
        $extra = unserialize(serialize(self::VERSION_AE2_2));
        data_set($extra, 'parentProjectId', self::VERSION_AE2_1['id']);
        Http::fake([
            sprintf('https://api.curseforge.com/v1/mods/%s/files/%s', self::PROJECT_AE2['id'], self::VERSION_AE2_1['id'])
                => Http::response(['data' => self::VERSION_AE2_1]),
            sprintf('https://www.curseforge.com/api/v1/mods/%s/files/%s/additional-files', self::PROJECT_AE2['id'], self::VERSION_AE2_1['id'])
                => Http::response(['data' => [Arr::only($extra, ['id'])]]),
            'https://api.curseforge.com/v1/mods/files' => Http::response([
                'data' => [$extra]
            ])
        ]);

        $response = $this->api->getVersionFiles(self::PROJECT_AE2['id'], self::VERSION_AE2_1['id']);

        Http::assertSentCount(3);
        $this->assertNull($response->getPagination());
        $this->assertSame(2, $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(FileDTO::class, $response->getData());
        $this->assertNotNull($file1 = $response->getData()->first(
            fn(FileDTO $f) => $f->remoteId === (string)self::VERSION_AE2_1['id'])
        );
        $this->assertNotNull($file2 = $response->getData()->first(
            fn(FileDTO $f) => $f->remoteId === (string)$extra['id'])
        );
        $this->assertSame(true, $file1->primary);
        $this->assertSame(false, $file2->primary);
    }

    public function test_get_version_dependencies()
    {
        Http::fake([
            sprintf('https://api.curseforge.com/v1/mods/%s/files/%s', self::PROJECT_AE2['id'], self::VERSION_AE2_1['id'])
                => Http::response(['data' => self::VERSION_AE2_1]),
            'https://api.curseforge.com/v1/mods' => Http::response([
                'data' => [self::PROJECT_GUIDEME]
            ]),
        ]);

        $response = $this->api->getVersionDependencies(self::PROJECT_AE2['id'], self::VERSION_AE2_1['id']);

        Http::assertSentCount(2);
        $this->assertNull($response->getPagination());
        $this->assertSame(1, $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(ProjectDTO::class, $response->getData());
        /** @var ProjectDTO $project */
        $this->assertNotNull($project = $response->getData()->first(fn(ProjectDTO $p) => $p->remoteId === (string)self::PROJECT_GUIDEME['id']));
        $this->assertSame(ProjectDependencyType::REQUIRED, $project->dependencyType);
    }

    public function test_get_version_dependants()
    {
        $this->expectException(UnsupportedApiMethodException::class);
        $this->api->getVersionDependants(123, 456);
    }

    public function test_get_project_authors()
    {
        Http::fake(['https://api.curseforge.com/v1/mods/'.self::PROJECT_AE2['id'] => Http::response([
            'data' => self::PROJECT_AE2
        ])]);

        $response = $this->api->getProjectAuthors(self::PROJECT_AE2['id']);

        Http::assertSentCount(1);
        $this->assertNull($response->getPagination());
        $this->assertSame(count(self::PROJECT_AE2['authors']), $response->getData()->count());
        $this->assertContainsOnlyInstancesOf(AuthorDTO::class, $response->getData());
        $this->assertEqualsCanonicalizing(
            Arr::pluck(self::PROJECT_AE2['authors'], 'id'),
            $response->getData()->map(fn(AuthorDTO $a) => $a->remoteId)->toArray()
        );
    }

    public function test_get_categories()
    {
        Http::fake(['https://api.curseforge.com/v1/categories*' => Http::response([
            'data' => $categories = [...self::PROJECT_AE2['categories'], ...self::CATEGORIES]
        ])]);

        $response = $this->api->getCategories();

        Http::assertSentCount(1);
        $this->assertSame(count($categories), $response->count());
        $this->assertContainsOnlyInstancesOf(CategoryDTO::class, $response);
        $this->assertEqualsCanonicalizing(
            Arr::pluck($categories, 'id'),
            $response->map(fn(CategoryDTO $c) => $c->remoteId)->toArray()
        );
        $this->assertTrue($response
            ->filter(fn(CategoryDTO $c) => $c->parentId === '6')
            ->every(fn(CategoryDTO $c) => $c->projectTypes->count() === 1 && $c->projectTypes->first() === EProjectType::MOD)
        );
        $this->assertTrue($response
            ->filter(fn(CategoryDTO $c) => $c->parentId === '12')
            ->every(fn(CategoryDTO $c) => $c->projectTypes->count() === 1 && $c->projectTypes->first() === EProjectType::RESOURCE_PACK)
        );
    }

    public function test_get_loaders()
    {
        $response = $this->api->getLoaders();

        $this->assertContainsOnlyInstancesOf(LoaderDTO::class, $response);
    }
}
