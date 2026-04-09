<?php

namespace Tests\Feature;

use App\Enums\VersionType;
use App\Jobs\ArchiveProject;
use App\Jobs\RevalidateVersionJob;
use App\Mca\ApiManager;
use App\Models\ArchiveRule;
use App\Models\Author;
use App\Models\Category;
use App\Models\File;
use App\Models\GameVersion;
use App\Models\Loader;
use App\Models\Project;
use App\Models\Ruleset;
use App\Models\Version;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Queue;
use Tests\Constraints\JsonCollectionOrderedByDate;
use Tests\Laravel\RefreshDatabase;
use Tests\LocalDBThirdPartyApi;
use Tests\SettingsServiceFake;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    private SettingsServiceFake $appSettings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appSettings = $this->useAppSettings();
        $manager = app(ApiManager::class);
        $manager->registerApi(LocalDBThirdPartyApi::class);
    }

    /** @test */
    public function it_lists_projects()
    {
        $project = Project::factory()->create();
//        $projectTinkers = Project::factory()
//            ->has(Version::factory(3)->has(GameVersion::factory(), 'game_versions'))
//            ->create(['name' => 'Tinkers Construct']);

        // Test basic
        $this->get(route('project.index', ['archived_only' => true]));
        $this->response->assertOk()->assertJsonPath('data.0.name', $project->name);

        // Test platform filtering
        $this->get(route('project.index', ['archived_only' => true, 'platform' => $project->platform]));
        $this->response->assertOk()->assertJsonCount(1, 'data');

        // Test unknown platform
        $this->get(route('project.index', ['archived_only' => true, 'platform' => 'foobar']));
        $this->response->assertUnprocessable();

        // Test excluding IDs
        $this->get(route('project.index', ['archived_only' => true, 'exclude_ids' => $project->only('id')]));
        $this->response->assertOk()->assertJsonCount(0, 'data');

        // Test excluding remote IDs
        $this->get(route('project.index', ['archived_only' => true, 'exclude_remote' => array_values($project->only(['platform', 'remote_id']))]));
        $this->response->assertOk()->assertJsonCount(0, 'data');

        // Test search
        $this->get(route('project.index', ['archived_only' => true, 'query' => 'foobar']));
        $this->response->assertOk();
        $this->response->assertJsonCount(0, 'data');

        $loader = Loader::factory()->create(['name' => 'Forge']);
        $projectTinkers = Project::factory()
            ->has(Version::factory(3)
                ->has(GameVersion::factory(), 'game_versions')
                ->hasAttached($loader)
            )
            ->has(Category::factory(2))
            ->create(['name' => 'Tinkers Construct']);

        $this->get(route('project.index', ['archived_only' => true, 'query' => 'construct']));
        $this->response
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Tinkers Construct')
            ->assertJsonCount(1, 'data');

        // Test game version search
        $this->get(route('project.index', ['archived_only' => true, 'game_versions' => [GameVersion::first()->name]]));
        $this->response
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Tinkers Construct')
            ->assertJsonCount(1, 'data');

        // Test non-existent game version search
        $this->get(route('project.index', ['archived_only' => true, 'game_versions' => ['1.12.3']]));
        $this->response->assertOk()->assertJsonCount(0, 'data');

        // Test loader search
        $this->get(route('project.index', ['archived_only' => true, 'loaders' => [$loader->getKey()]]));
        $this->response
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Tinkers Construct')
            ->assertJsonCount(1, 'data');

        // Test non-existent loader search
        $this->get(route('project.index', ['archived_only' => true, 'loaders' => [99]]));
        $this->response->assertOk()->assertJsonCount(0, 'data');

        // Test loader string search
        $this->get(route('project.index', ['archived_only' => true, 'loaders' => ['ModLoader']]));
        $this->response->assertUnprocessable();

        // Test category search
        $this->get(route('project.index', ['archived_only' => true, 'categories' => [Category::first()->id]]));
        $this->response
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Tinkers Construct')
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function it_lists_remote_projects()
    {
        $project = Project::factory()->create();

        $this->get(route('project.index', ['platform' => 'local']));
        $this->response->assertOk()->assertJsonPath('data.0.name', $project->name);
    }

    /** @test */
    public function it_displays_a_project()
    {
        // Local
        $project = Project::factory()->create();

        $this->get(route('project.show', ['archived_only' => true, 'id' => $project->master_project_id]));
        $this->response->assertOk()->assertJsonPath('data.name', $project->name);
        $this->assertJsonPathNotNull('data.game_versions');
        $this->assertJsonPathNotNull('data.loaders');

        $this->get(route('project.show', ['archived_only' => true, 'id' => 123]));
        $this->response->assertNotFound();

        // Remote
        $project = Project::factory()->create();

        $this->get(route('project.show', ['id' => $project->master_project_id, 'platform' => 'local']));
        $this->response->assertOk()->assertJsonPath('data.name', $project->name);

        $this->get(route('project.show', ['id' => 123, 'platform' => 'local']));
        $this->response->assertNotFound();
    }

    /** @test */
    public function it_archives_the_project()
    {
        $project = Project::factory()->create();

        // New rules
        $this->post(route('project.archive', ['id' => $project->getKey()]), [
            'platform_id' => 'local',
            'archived_only' => true,
            'rules' => [
                [
                    'count' => 1,
                    'sorting' => false,
                    'release_type' => '*',
                    'release_type_priority' => false,
                    'game_version_from' => '*',
                    'game_version_to' => null,
                    'with_snapshots' => false,
                    'loader_id' => '*',
                    'dependencies' => 0,
                    'all_files' => false
                ]
            ]
        ]);

        $this->response->assertOk();
        $this->assertDatabaseCount('archive_rules', 1);
        $rule = ArchiveRule::query()->first();
        $this->assertEquals($ruleRaw = [
            'count' => 1,
            'sorting' => 0,
            'release_type' => null,
            'release_type_priority' => 0,
            'game_version_from' => '*',
            'game_version_to' => null,
            'with_snapshots' => 0,
            'loader_id' => null,
            'dependencies' => 0,
            'all_files' => 0
        ], Arr::only($rule->getAttributes(), array_keys($ruleRaw)));

        // Update rules
        $this->post(route('project.archive', ['id' => $project->getKey()]), [
            'platform_id' => 'local',
            'archived_only' => true,
            'rules' => [
                [
                    'id' => $rule->getKey(),
                    'count' => 2,
                    'sorting' => true,
                    'release_type' => 1,
                    'release_type_priority' => true,
                    'game_version_from' => '*',
                    'game_version_to' => null,
                    'with_snapshots' => true,
                    'loader_id' => '*',
                    'dependencies' => 1,
                    'all_files' => true
                ]
            ]
        ]);

        $this->response->assertOk();
        $this->assertDatabaseCount('archive_rules', 1);
        $rule = ArchiveRule::query()->first();
        $this->assertEquals($ruleRaw = [
            'count' => 2,
            'sorting' => 1,
            'release_type' => 1,
            'release_type_priority' => 1,
            'game_version_from' => '*',
            'game_version_to' => null,
            'with_snapshots' => 1,
            'loader_id' => null,
            'dependencies' => 1,
            'all_files' => 1
        ], Arr::only($rule->getAttributes(), array_keys($ruleRaw)));
    }

    /** @test */
    public function it_archives_the_project_with_ruleset_rules()
    {
        $project = Project::factory()->create();
        $ruleset = Ruleset::factory()->has(ArchiveRule::factory(3), 'archive_rules')->create();

        // Copy from ruleset
        $this->post(route('project.archive', ['id' => $project->getKey()]), [
            'platform_id' => 'local',
            'archived_only' => true,
            'ruleset_id' => $ruleset->getKey()
        ]);

        $this->response->assertOk();
        $this->assertDatabaseCount('archive_rules', 6); // 3 from ruleset + 3 copied

        $getRulesRawAttributes = fn(Collection $rules) => $rules->map(
            fn(ArchiveRule $r) => Arr::except($r->getAttributes(), ['id', 'ruleable_type', 'ruleable_id'])
        );

        $this->assertEquals(
            $getRulesRawAttributes($ruleset->archive_rules),
            $getRulesRawAttributes($project->archive_rules)
        );
    }

    /** @test */
    public function it_archives_revalidates_a_version()
    {
        Queue::fake();
        $project = Project::factory()->has(Version::factory())->create();
        $version = $project->versions->first();

        // Archive
        $this->json(
            'post',
            route('project.version.archive', ['id' => $project->remote_id, 'versionId' => '321']),
            ['platform' => 'local', 'file_ids' => ['12', '34']]
        );

        $this->response->assertCreated();
        Queue::assertPushed(ArchiveProject::class);

        // Revalidate - local
        $this->post(route('project.version.revalidate', [
            'id' => $project->getKey(), 'versionId' => $version->getKey()
        ]));
        $this->response->assertCreated();
        Queue::assertPushed(RevalidateVersionJob::class);

        $this->post(route('project.version.revalidate', ['id' => $project->getKey(), 'versionId' => 4321]));
        $this->response->assertNotFound();

        // Revalidate - remote
        $this->post(route('project.version.revalidate', [
            'id' => $project->getKey(), 'versionId' => $version->remote_id, 'platform' => $version->platform
        ]));
        $this->response->assertCreated();
        Queue::assertPushed(RevalidateVersionJob::class);

        $this->post(route('project.version.revalidate', [
            'id' => $project->getKey(), 'versionId' => 4321, 'platform' => $version->platform
        ]));
        $this->response->assertNotFound();
    }

    /** @test */
    public function it_lists_authors()
    {
        $project = Project::factory()
            ->hasAttached(Author::factory(), ['role' => 'Developer'])
            ->hasAttached(Author::factory(), ['role' => 'Mascot'])
            ->create();

        // Local
        $this->get(route('project.authors', ['archived_only' => true, 'id' => $project->getKey()]));
        $this->response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.role', 'Developer')
            ->assertJsonPath('data.1.role', 'Mascot');

        $this->get(route('project.authors', ['archived_only' => true, 'id' => 123]));
        $this->response->assertNotFound();

        // Remote
        $this->get(route('project.authors', ['platform' => 'local', 'id' => $project->getKey()]));
        $this->response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.role', 'Developer')
            ->assertJsonPath('data.1.role', 'Mascot');

        $this->get(route('project.authors', ['platform' => 'local', 'id' => 123]));
        $this->response->assertNotFound();
    }

    /** @test */
    public function it_lists_dependencies_and_dependants()
    {
        $dependency = Project::factory()
            ->has(Version::factory())
            ->create();

        $project = Project::factory()
            ->has(Version::factory()->hasAttached($dependency, ['type' => 0], 'dependencies'))
            ->create();

        // Dependencies
        $this->get(route('project.dependencies', ['archived_only' => true, 'id' => $project->getKey()]));
        $this->response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', $dependency->name);

        $this->get(route('project.dependencies', ['archived_only' => true, 'id' => 123]));
        $this->response->assertNotFound();

        // Dependants
        $this->get(route('project.dependants', ['archived_only' => true, 'id' => $dependency->getKey()]));
        $this->response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', $project->name);

        $this->get(route('project.dependants', ['archived_only' => true, 'id' => 123]));
        $this->response->assertNotFound();
    }

    /** @test */
    public function it_lists_versions()
    {
        $gameVersion = GameVersion::factory()->create(['name' => '25w01a']);
        $loaderForge = Loader::factory()->create(['name' => 'Forge']);
        $loaderFabric = Loader::factory()->create(['name' => 'Fabric']);
        $project = Project::factory()
            ->has(Version::factory(2, ['type' => VersionType::BETA->value])->has(GameVersion::factory(), 'game_versions')->hasAttached($loaderForge))
            ->has(Version::factory(1, ['type' => VersionType::RELEASE->value])->hasAttached($gameVersion, relationship: 'game_versions')->hasAttached($loaderFabric))
            ->create();

        // Local
        $this->get(route('project.versions', ['archived_only' => true, 'id' => $project->getKey()]));
        $this->response->assertOk()->assertJsonCount(3, 'data');
        $this->assertThat($this->response, new JsonCollectionOrderedByDate('published_at', 'desc', 'data'));

        // Test loader filtering
        $this->get(route('project.versions', ['archived_only' => true, 'id' => $project->getKey(), 'loaders' => [$loaderFabric->getKey()]]));
        $this->response->assertOk()->assertJsonCount(1, 'data');

        // Test game version filtering
        $this->get(route('project.versions', ['archived_only' => true, 'id' => $project->getKey(), 'game_versions' => [$gameVersion->name]]));
        $this->response->assertOk()->assertJsonCount(1, 'data');

        // Test release type filtering
        $this->get(route('project.versions', ['archived_only' => true, 'id' => $project->getKey(), 'release_types' => [VersionType::RELEASE->value]]));
        $this->response->assertOk()->assertJsonCount(1, 'data');

        // Local by remote ID
        $this->get(route('project.versions', ['archived_only' => true, 'id' => $project->remote_id, 'platform' => 'local']));
        $this->response->assertOk()->assertJsonCount(3, 'data');

        // Missing project
        $this->get(route('project.versions', ['archived_only' => true, 'id' => 123]));
        $this->response->assertNotFound();

        // Remote
        $this->get(route('project.versions', ['platform' => 'local', 'id' => $project->getKey()]));
        $this->response->assertOk()->assertJsonCount(3, 'data');
    }

    /** @test */
    public function it_lists_version_dependencies_and_dependants()
    {
        $dependency = Project::factory()->has(Version::factory())->create();
        $dependencyVersion = $dependency->versions->first();

        $project = Project::factory()
            ->has(Version::factory(2)->hasAttached($dependencyVersion, ['dependency_project_id' => $dependency->getKey(), 'type' => 0], 'dependencies_versions'))
            ->create();
        $projectVersion = $project->versions->first();

        // Dependencies
        $this->get(route('project.version.dependencies', ['archived_only' => true, 'id' => $project->getKey(), 'versionId' => $projectVersion->getKey()]));
        $this->response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $dependency->name)
            ->assertJsonPath('data.0.dependency_versions.0.name', $dependencyVersion->version);

        $this->get(route('project.version.dependencies', ['archived_only' => true, 'id' => 123, 'versionId' => 456]));
        $this->response->assertNotFound();

        // Dependants
        $this->get(route('project.version.dependants', ['archived_only' => true, 'id' => $dependency->getKey(), 'versionId' => $dependencyVersion->getKey()]));
        $this->response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $project->name)
            ->assertJsonPath('data.0.dependency_versions.0.name', $projectVersion->version);

        $this->get(route('project.version.dependants', ['archived_only' => true, 'id' => 123, 'versionId' => 456]));
        $this->response->assertNotFound();

        // Test that endpoint does not return duplicate projects
        $this->get(route('project.version.dependants', ['archived_only' => true, 'id' => $dependency->getKey(), 'versionId' => $dependencyVersion->getKey()]));
        $this->response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.dependency_versions');
    }

    /** @test */
    public function it_lists_version_files()
    {
        // Local
        $project = Project::factory()
            ->has(Version::factory()->has(File::factory()))
            ->create();
        $version = $project->versions->first();
        $file = $version->files->first();

        $this->get(route('project.version.files', [
            'archived_only' => true, 'platform' => 'local', 'id' => $project->getKey(), 'versionId' => $version->getKey()
        ]));
        $this->response->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', $file->original_file_name);

        $this->get(route('project.version.files', ['archived_only' => true, 'platform' => 'local', 'id' => 123, 'versionId' => 456]));
        $this->response->assertNotFound();

        // Remote
        $this->get(route('project.version.files', ['platform' => 'local', 'id' => $project->remote_id, 'versionId' => $version->remote_id]));
        $this->response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', $file->original_file_name)
            ->assertJsonPath('0.local', true);
    }

    /** @test */
    public function it_deletes_version()
    {
        $project = Project::factory()->has(Version::factory()->has(File::factory()))->create();
        $version = $project->versions->first();
        $file = $version->files->first();

        $fileOnDisk = $this->makeExampleFile($version->getStorageArea(), $file);
        $filePath = $fileOnDisk->getRealPath();

        $this->delete(route('project.version.delete', ['id' => $project->getKey(), 'versionId' => $version->getKey()]));
        $this->response->assertNoContent();
        $this->assertNull($file->fresh());
        $this->assertNull($version->fresh());
        $this->assertFileDoesNotExist($filePath);
        $this->assertDirectoryDoesNotExist(Str::beforeLast($filePath, '\\'));

        $this->delete(route('project.version.delete', ['id' => 123, 'versionId' => 456]));
        $this->response->assertNotFound();
    }

    /** @test */
    public function it_deletes_a_file()
    {
        $project = Project::factory()->has(Version::factory()->has(File::factory(2)))->create();
        $version = $project->versions->first();
        $file = $version->files->first();

        $fileOnDisk = $this->makeExampleFile($version->getStorageArea(), $file);
        $filePath = $fileOnDisk->getRealPath();

        $this->delete(route('project.version.files.delete', [
            'id' => $project->getKey(), 'versionId' => $version->getKey(), 'fileId' => $file->getKey()
        ]));
        $this->response->assertNoContent();
        $this->assertNull($file->fresh());
        $this->assertNotNull($version->fresh());
        $this->assertFileDoesNotExist($filePath);
        $this->assertDirectoryDoesNotExist(Str::beforeLast($filePath, '\\'));

        $this->delete(route('project.version.files.delete', ['id' => 123, 'versionId' => 345, 'fileId' => 678]));
        $this->response->assertNotFound();
    }

    /** @test */
    public function it_prevents_version_delete_if_it_has_dependants()
    {
        $project = Project::factory()->has(Version::factory()->has(File::factory()))->create();
        $version = $project->versions->first();
        $file = $version->files->first();

        $dependant = Project::factory()
            ->has(Version::factory()->hasAttached(
                $version, ['dependency_project_id' => $project->getKey(), 'type' => 0], 'dependencies_versions'
            ))
            ->create();

        $this->delete(route('project.version.delete', ['id' => $project->getKey(), 'versionId' => $version->getKey()]));
        $this->response->assertUnprocessable();
        $this->assertNotNull($file->fresh());
    }

    /** @test */
    public function it_removes_a_child_dependency_if_unused()
    {
        $dependency = Project::factory()->has(Version::factory()->has(File::factory()))->create();
        $dependencyVersion = $dependency->versions->first();
        $dependencyFile = $dependencyVersion->files->first();

        $project = Project::factory()->has(
            Version::factory()
                ->has(File::factory())
                ->hasAttached($dependencyVersion, ['dependency_project_id' => $dependency->getKey(), 'type' => 0], 'dependencies_versions')
        )->create();
        $version = $project->versions->first();
        $file = $version->files->first();

        $dependencyFilePath = $this->makeExampleFile($dependencyVersion->getStorageArea(), $dependencyFile)->getRealPath();
        $filePath = $this->makeExampleFile($version->getStorageArea(), $file)->getRealPath();

        $this->delete(route('project.version.delete', ['id' => $project->getKey(), 'versionId' => $version->getKey()]));
        $this->response->assertNoContent();
        $this->assertNull($file->fresh());
        $this->assertNull($dependencyFile->fresh());
        $this->assertFileDoesNotExist($filePath);
        $this->assertFileDoesNotExist($dependencyFilePath);
    }

    /** @test */
    public function it_merges_unmerges_sets_default_projects()
    {
        $p1 = Project::factory()->has(Version::factory())->create();
        $p2 = Project::factory()->has(Version::factory())->create();

        // Merge
        $this->post(route('project.merge'), [
            'project_id' => $p1->master_project_id, 'merged_project_id' => $p2->master_project_id,
            'project_is_remote' => false, 'project_platform' => $p1->platform, 'merge_direction_reverse' => false
        ]);
        $this->response->assertOk();
        $this->assertSame($p1->refresh()->master_project_id, $p2->refresh()->master_project_id);
        $this->assertSame($p1->getKey(), $p2->master_project->preferred_project_id);
        $this->assertDatabaseCount('master_projects', 1);

        $this->post(route('project.merge'), [
            'project_id' => 123, 'merged_project_id' => $p2->master_project_id,
            'project_is_remote' => false, 'project_platform' => $p1->platform, 'merge_direction_reverse' => false
        ]);
        $this->response->assertNotFound();

        $this->post(route('project.merge'), [
            'project_id' => $p1->master_project_id, 'merged_project_id' => 123,
            'project_is_remote' => false, 'project_platform' => $p1->platform, 'merge_direction_reverse' => false
        ]);
        $this->response->assertUnprocessable();

        // Related
        $this->post(route('project.related', ['id' => $p1->master_project_id]));
        $this->response->assertOk()->assertJsonCount(2, 'data');

        // Related by remote ID
        $this->post(route('project.related', ['id' => $p1->remote_id, 'platform' => $p1->platform]));
        $this->response->assertOk()->assertJsonCount(2, 'data');

        // Unmerge
        $this->post(route('project.unmerge', ['id' => $p2->getKey()]));
        $this->response->assertNoContent();
        $p1->refresh(); $p2->refresh();
        $this->assertNotSame($p1->master_project_id, $p2->master_project_id);
        $this->assertNotSame($p1->master_project->archive_dir, $p2->master_project->archive_dir);
        $this->assertDatabaseCount('master_projects', 2);

        $this->post(route('project.unmerge', ['id' => 321]));
        $this->response->assertNotFound();

        // Can not unmerge if not merged
        $this->post(route('project.unmerge', ['id' => $p2->getKey()]));
        $this->response->assertUnprocessable();

        // Reverse direction
        $this->post(route('project.merge'), [
            'project_id' => $p1->master_project_id, 'merged_project_id' => $p2->master_project_id,
            'project_is_remote' => false, 'project_platform' => $p1->platform, 'merge_direction_reverse' => true
        ]);
        $this->response->assertOk();
        $this->assertSame($p1->refresh()->master_project_id, $p2->refresh()->master_project_id);
        $this->assertSame($p2->getKey(), $p1->master_project->preferred_project_id);
        $this->assertDatabaseCount('master_projects', 1);

        // Set default
        $this->post(route('project.set-default', ['id' => $p1->getKey()]));
        $this->response->assertNoContent();
        $this->assertSame($p1->refresh()->getKey(), $p1->master_project->preferred_project_id);
    }

    /** @test */
    public function it_prevents_merging_same_project()
    {
        $project = Project::factory()->has(Version::factory())->create();

        $this->post(route('project.merge'), [
            'project_id' => $project->master_project_id, 'merged_project_id' => $project->master_project_id,
            'project_is_remote' => false, 'project_platform' => $project->platform, 'merge_direction_reverse' => false
        ]);
        $this->response->assertUnprocessable();
    }

    /** @test */
    public function it_downloads_a_local_file()
    {
        $project = Project::factory()->has(Version::factory()->has(File::factory()))->create();
        $version = $project->versions->first();
        $file = $version->files->first();
        $this->makeExampleFile($version->getStorageArea(), $file);

        $this->get(route('download', ['id' => $file->getKey()]));
        $this->response->assertOk()->assertDownload($file->original_file_name);
    }
}
