<?php

namespace Tests\Feature;

use App\API\Contracts\ThirdPartyApi;
use App\API\DTO\VersionDTO;
use App\API\DTO\ProjectDTO;
use App\API\ThirdPartyApiResponse;
use App\Enums\VersionType;
use App\Mca\ApiManager;
use App\Models\ArchiveRule;
use App\Models\File;
use App\Models\GameVersion;
use App\Models\Loader;
use App\Models\MasterProject;
use App\Models\Project;
use App\Models\Version;
use App\Services\McaDownloader;
use App\Services\McaRulesetArchiver;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Tests\Laravel\RefreshDatabase;
use Tests\TestCase;

class RulesetArchiverTest extends TestCase
{
    use RefreshDatabase;

    protected array $mockedApis = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAppSettings();
    }

    protected function getMockedApi($id, array $methods)
    {
        if (! isset($this->mockedApis[$id])) {
            $api = $this->createConfiguredMockWithCallbacks(ThirdPartyApi::class, ['id' => $id, ...$methods]);
            $this->mockedApis[$id] = $api;
        }

        return $this->mockedApis[$id];
    }

    protected function getMockedThirdPartyResponse($data)
    {
        return $this->createConfiguredMockWithCallbacks(ThirdPartyApiResponse::class, [
            'getData' => $data
        ]);
    }

    protected function loadData(string $class, string $file, bool $asCollection = true)
    {
        $data = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        if (! $asCollection) {
            return $class::fromArray($data);
        }

        return collect(array_map(fn(array $v) => $class::fromArray($v), $data));
    }

    protected function loadDataFiles(array|string $files): array
    {
        return Arr::map(Arr::wrap($files), fn($v) => $this->loadData(VersionDTO::class, $this->getTestDataDir('version', $v)));
    }

    protected function makeGuzzle($autoMakeFile = false): Client
    {
        $handler = new MockHandler();
        $handlerStack = HandlerStack::create($handler);
        $middleware = function (callable $handler) use ($autoMakeFile) {
            return function (RequestInterface $request, array $options) use ($autoMakeFile, $handler) {
                $lastUrlPart = $request->getUri()->getPath();
                $file = Str::afterLast($lastUrlPart, '/');

                /** @var MockHandler $handler */
                if (str_contains($file, '.') && file_exists($filePath = $this->getTestDataDir('version', $file))) {
                    $handler->append(new Response(200, [], file_get_contents($filePath)));
                }
                elseif($autoMakeFile) {
                    $fileInfo = $this->makeFile($this->getTestDataDir(), $file);
                    $handler->append(new Response(200, [], file_get_contents($fileInfo->getRealPath())));
                }
                else {
                    Log::debug('404!!');
                    $handler->append(new Response(404, []));
                }

                return $handler($request, $options);
            };
        };

        $handlerStack->push($middleware);
        return new Client(['handler' => $handlerStack]);
    }

    protected function setUpMockedApi(array $mockApiFunctions)
    {
        $apiManager = $this->createMock(ApiManager::class);
        $apiManager->method('get')->willReturnCallback(fn($id) => $this->getMockedApi($id, $mockApiFunctions));
        $this->instance(ApiManager::class, $apiManager);
    }

    protected function makeGameVersions()
    {
        GameVersion::factory()->count(3)->sequence(
            ['name' => '1.18.2', 'released_at' => '2022-02-28T10:42:45.000000Z'],
            ['name' => '1.19.2', 'released_at' => '2022-08-05T11:57:05.000000Z'],
            ['name' => '1.20.1', 'released_at' => '2023-06-12T13:25:51.000000Z']
        )->create();
    }

    protected function setUpTest(string $dataFile)
    {
        $data = $this->loadData(VersionDTO::class, $this->getTestDataDir('version', $dataFile));
        $this->makeGameVersions();
        $this->instance(Client::class, $this->makeGuzzle());
        $this->setUpMockedApi([
            'getProjectVersionsToDate' => $data,
            'getAllProjectVersions' => $this->getMockedThirdPartyResponse($data)
        ]);

        return $data;
    }

    /** @test */
    public function it_correctly_archives_versions()
    {
        $versions = $this->setUpTest('tinkers.json');

        $project = Project::factory()
            ->has(ArchiveRule::factory()->forGameVersion('1.20.1'), 'archive_rules')
            ->create(['name' => 'Tinkers Construct', 'platform' => 'local']);

        $archiver = app(McaRulesetArchiver::class);
        $archiver->archive($project->master_project);

        $versionsSaved = $versions
            ->filter(fn(VersionDTO $v) => $v->hasGameVersion('1.20.1'))
            ->take(1);

        $this->assertDatabaseCount('versions', 1);
        $this->assertDatabaseCount('files', 1);

        /** @var VersionDTO $version */
        foreach ($versionsSaved as $version) {
            $this->seeInDatabase('files', ['remote_id' => $version->getPrimaryFile()->remoteId]);
        }

        $this->assertNotNull($project->refresh()->last_version_check, 'Last check date not updated');

        // Should not archive anything as nothing changed
        $archiver->archive($project->master_project);

        $this->assertDatabaseCount('versions', 1);
        $this->assertDatabaseCount('files', 1);

        /** @var VersionDTO $version */
        foreach ($versionsSaved as $version) {
            $this->seeInDatabase('files', ['remote_id' => $version->getPrimaryFile()->remoteId]);
        }
    }

    /** @test */
    public function it_archives_versions_for_specified_loader()
    {
        $versions = $this->setUpTest('tinkers.json');

        $loader = Loader::forceCreate(['name' => 'Forge', 'slug' => 'forge']);
        $loader->remotes()->forceCreate(['remote_id' => 'forge', 'platform' => 'local']);
        $project = Project::factory()
            ->has(ArchiveRule::factory()->for($loader)->forGameVersion('1.20.1'), 'archive_rules')
            ->create(['name' => 'Tinkers Construct', 'platform' => 'local']);

        $archiver = app(McaRulesetArchiver::class);
        $archiver->archive($project->master_project);

        $versionSaved = $versions
            ->filter(fn(VersionDTO $v) => $v->hasGameVersion('1.20.1') && $v->hasLoader('Forge'))
            ->first();

        $this->assertDatabaseCount('versions', 1);
        $this->assertDatabaseCount('files', 1);
        $this->seeInDatabase('files', ['remote_id' => $versionSaved->getPrimaryFile()->remoteId]);

        $this->assertNotNull($project->refresh()->last_version_check, 'Last check date not updated');
        $version = Version::query()->with('files')->first();
        $this->assertFileExists($version->files->first()->getAbsoluteFilePath($version->getStorageArea()));
    }

    /** @test */
    public function it_correctly_prioritizes_release_types()
    {
        $versions = $this->setUpTest('tinkers.json');

        $project = Project::factory()
            ->has(ArchiveRule::factory()->forGameVersion('1.20.1')->withReleasePriority(), 'archive_rules')
            ->create(['name' => 'Tinkers Construct', 'remote_id' => 'tinkers', 'platform' => 'local']);

        $archiver = app(McaRulesetArchiver::class);
        $archiver->archive($project->master_project);

        $versionsSaved = $versions
            ->filter(fn(VersionDTO $v) => $v->hasGameVersion('1.20.1') && $v->type === VersionType::RELEASE)
            ->take(1);

        $this->assertDatabaseCount('versions', $versionsSaved->count());
        $this->assertDatabaseCount('files', 1);

        /** @var VersionDTO $version */
        foreach ($versionsSaved as $version) {
            $this->seeInDatabase('files', ['remote_id' => $version->getPrimaryFile()->remoteId]);
        }

        $this->assertNotNull($project->refresh()->last_version_check, 'Last check date not updated');
    }

    /** @test */
    public function it_correctly_archives_version_and_its_dependencies()
    {
        ['tinkers' => $tinkers, 'mantle' => $mantle] = $this->loadDataFiles(['tinkers' => 'tinkers.json', 'mantle' => 'mantle.json']);
        /** @var ProjectDTO $mantleProjectDto */
        $mantleProjectDto = $this->loadData(ProjectDTO::class, $this->getTestDataDir('project-mantle.json'), false);
        $this->makeGameVersions();
        $this->instance(Client::class, $this->makeGuzzle());
        $this->setUpMockedApi([
            'getProject' => fn($projectId) => $projectId === 'mantle'
                ? $this->getMockedThirdPartyResponse($mantleProjectDto)
                : throw new \RuntimeException('Invalid id'),
            'getLoaders' => $mantleProjectDto->loaders,
            'getCategories' => $mantleProjectDto->categories,
            'getProjectVersionsToDate' => fn($projectId) => $projectId === 'tinkers' ? $tinkers : $mantle,
            'getAllProjectVersions' => fn($projectId) => $projectId === 'tinkers'
                ? $this->getMockedThirdPartyResponse($tinkers)
                : $this->getMockedThirdPartyResponse($mantle),
            'getProjectVersionsForGameVersions' => fn($projectId) => $projectId === 'mantle'
                ? $this->getMockedThirdPartyResponse($mantle->filter(fn(VersionDTO $v) => $v->hasGameVersion('1.20.1')))
                : throw new \RuntimeException('Invalid id')
        ]);

        $project = Project::factory()
            ->has(ArchiveRule::factory()->forGameVersion('1.20.1')->withRequiredDependencies(), 'archive_rules')
            ->create(['name' => 'Tinkers Construct', 'remote_id' => 'tinkers', 'platform' => 'local']);
        $projectMantle = Project::factory()->create(['name' => 'Mantle', 'remote_id' => 'mantle', 'platform' => 'local']);

        $archiver = app(McaRulesetArchiver::class);
        $archiver->archive($project->master_project);

        $this->assertDatabaseCount('versions', 2);
        $this->assertDatabaseCount('files', 2);
        $this->seeInDatabase('files', ['remote_id' => 'TConstruct-1.20.1-3.10.2.92.jar']);
        $this->seeInDatabase('files', ['remote_id' => 'Mantle-1.20.1-1.11.79.jar']);

        $this->assertNotNull($project->refresh()->last_version_check, 'Last check date not updated');
        $versions = Version::query()->with('files')->get();
        foreach ($versions as $version) {
            $this->assertFileExists($version->files->first()->getAbsoluteFilePath($version->getStorageArea()));
        }
    }

    /** @test */
    public function it_does_not_archive_duplicate_versions()
    {
        $this->makeGameVersions();
        $this->instance(Client::class, $this->makeGuzzle(true));
        $this->instance(McaDownloader::class, app(McaDownloader::class)->setSkipVerify());
        $mp = MasterProject::factory()->create(['name' => 'Tinkers Construct']);
        $project1 = Project::factory()
            ->for($mp, 'master_project')
            ->has(ArchiveRule::factory(), 'archive_rules')
            ->create(['name' => 'Tinkers Construct [1]', 'platform' => 'remote']);
        $project2 = Project::factory()
            ->for($mp, 'master_project')
            ->has(ArchiveRule::factory(), 'archive_rules')
            ->create(['name' => 'Tinkers Construct [2]', 'platform' => 'local']);

        $version1 = Version::factory()->forGameVersionsStr('1.20.1')->make(['platform' => 'remote']);
        $version1->setRelation('files', new Collection([File::factory()->withHash(['sha1' => $this->exampleFileSha1])->make()]));
        $version2 = Version::factory()->forGameVersionsStr('1.20.1')->make(['platform' => 'local']);
        $version2->setRelation('files', new Collection([File::factory()->withHash(['sha1' => $this->exampleFileSha1])->make()]));

        $this->setUpMockedApi([
            'getAllProjectVersions' => fn($projectId) => $projectId === $project1->remote_id
                ? $this->getMockedThirdPartyResponse(collect([VersionDTO::fromLocal($version1)]))
                : $this->getMockedThirdPartyResponse(collect([VersionDTO::fromLocal($version2)])),
        ]);

        $archiver = app(McaRulesetArchiver::class);
        $archiver->archive($mp);

        $this->assertDatabaseCount('versions', 1);
        $this->assertDatabaseCount('files', 1);
    }

    /** @test */
    public function it_automatically_replaces_old_version_if_new_one_is_available()
    {
        $this->makeGameVersions();
        $this->instance(Client::class, $this->makeGuzzle(true));
        $this->instance(McaDownloader::class, app(McaDownloader::class)->setSkipVerify());

        $dependency = Project::factory()->has(Version::factory()->has(File::factory()))->create();
        $dependencyVersion = $dependency->versions->first();

        $project = Project::factory()
            ->has(Version::factory(null, ['published_at' => Carbon::now()->subYear()])
                ->has(File::factory())
                ->hasAttached(GameVersion::firstWhere('name', '1.20.1'), [], 'game_versions')
                ->hasAttached($dependencyVersion, ['dependency_project_id' => $dependency->getKey(), 'type' => 0], 'dependencies_versions')
            )
            ->has(ArchiveRule::factory()->forGameVersion('1.20.1'), 'archive_rules')
            ->create();
        $localVersion = $project->versions()->first();

        $remoteVersion = Version::factory()->forGameVersionsStr('1.20.1')->make(['platform' => 'local']);
        $remoteVersion->setRelation('files', new Collection([File::factory()->make()]));

        $this->setUpMockedApi([
            'getAllProjectVersions' => fn($projectId) => $this->getMockedThirdPartyResponse(collect([VersionDTO::fromLocal($remoteVersion)])),
        ]);

        $archiver = app(McaRulesetArchiver::class);
        $archiver->archive($project->master_project);

        $this->assertDatabaseCount('versions', 1);
        $this->assertDatabaseCount('files', 1);
        $this->assertDatabaseMissing('versions', ['id' => $localVersion->getKey()]);
        $this->assertDatabaseHas('versions', ['remote_id' => $remoteVersion->remote_id]);
    }

    /** @test */
    public function it_correctly_filters_and_sorts_versions()
    {
        $this->makeGameVersions();

        $project = Project::factory()
            ->has(ArchiveRule::factory()->sortedByOldest()->forGameVersion('1.20.1'), 'archive_rules')
            ->create();

        $versionOld = Version::factory()->forGameVersionsStr('1.20.1')->make(['published_at' => Carbon::now()->subYear()]);
        $versionNew = Version::factory()->forGameVersionsStr('1.20.1')->make(['published_at' => Carbon::now()->subDay()]);

        $archiver = app(McaRulesetArchiver::class);
        $result = $archiver->findMatchingVersions(
            $project,
            $project->archive_rules,
            collect([$versionOld, $versionNew])->map(fn(Version $v) => VersionDTO::fromLocal($v))
        );

        $this->assertSame(1, $result->count());
        $this->assertSame((string)$versionOld->remote_id, $result->first()->remoteId);
    }

    /** @test */
    public function it_differentiates_versions_from_different_platforms_with_same_remote_ids()
    {
        $this->makeGameVersions();

        $project = Project::factory()
            ->has(ArchiveRule::factory(null, ['count' => 99])->forGameVersion('1.20.1'), 'archive_rules')
            ->create();

        $versionOld = Version::factory()->forGameVersionsStr('1.20.1')->make(['platform' => 'remote', 'remote_id' => 500]);
        $versionNew = Version::factory()->forGameVersionsStr('1.20.1')->make(['platform' => 'local', 'remote_id' => 500]);

        $archiver = app(McaRulesetArchiver::class);
        $result = $archiver->findMatchingVersions(
            $project,
            $project->archive_rules,
            collect([$versionOld, $versionNew])->map(fn(Version $v) => VersionDTO::fromLocal($v))
        );

        $this->assertSame(2, $result->count());
    }

    /** @test */
    public function it_archives_versions_for_different_loaders_if_not_specified_in_rules()
    {
        $this->makeGameVersions();

        $project = Project::factory()
            ->has(ArchiveRule::factory(null, ['count' => 1])->forGameVersion('1.20.1'), 'archive_rules')
            ->create();

        $versionForge = Version::factory()->forGameVersionsStr('1.20.1')
            ->has(Loader::factory(1, ['name' => 'Forge']))->create();
        $versionFabric = Version::factory()->forGameVersionsStr('1.20.1')
            ->has(Loader::factory(1, ['name' => 'Fabric']))->create();

        $archiver = app(McaRulesetArchiver::class);
        $result = $archiver->findMatchingVersions(
            $project,
            $project->archive_rules,
            collect([
                $versionForge->load('loaders'),
                $versionFabric->load('loaders')
            ])->map(fn(Version $v) => VersionDTO::fromLocal($v))
        );

        $this->assertSame(2, $result->count());
    }

    /** @test */
    public function it_uses_global_archive_rules()
    {
        $this->setUpTest('tinkers.json');
        $project = Project::factory()
            ->create(['name' => 'Tinkers Construct', 'platform' => 'local']);
        ArchiveRule::factory()->for($project->master_project, 'ruleable')->forGameVersion('1.20.1')->create();

        $archiver = app(McaRulesetArchiver::class);
        $archiver->archive($project->master_project);

        $this->assertDatabaseCount('versions', 1);
    }

    /** @test */
    public function it_prioritizes_project_archive_rules_if_exist()
    {
        $this->setUpTest('tinkers.json');
        $project = Project::factory()
            ->has(ArchiveRule::factory(null, ['count' => 1])->withReleasePriority()->forGameVersion('1.20.1'), 'archive_rules')
            ->create(['name' => 'Tinkers Construct', 'platform' => 'local']);
        ArchiveRule::factory()->for($project->master_project, 'ruleable')->forGameVersion('1.20.1')->create();

        $archiver = app(McaRulesetArchiver::class);
        $archiver->archive($project->master_project);

        $this->assertDatabaseCount('versions', 1);
        $this->assertSame(VersionType::RELEASE, Version::first()->type);
    }
}
