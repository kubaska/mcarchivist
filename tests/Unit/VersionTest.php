<?php

namespace Tests\Unit;

use App\Models\File;
use App\Models\Library;
use App\Models\Loader;
use App\Models\Version;
use Tests\Laravel\RefreshDatabase;
use Tests\TestCase;

class VersionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_removes_a_version()
    {
        $version = Version::factory()->create();
        $this->assertTrue($version->remove());
        $this->assertNull($version->fresh());
    }

    /** @test */
    public function it_tries_to_remove_a_user_made_version_without_forcing_and_fails()
    {
        $version = Version::factory()->has(File::factory()->createdByUser())->create();
        $this->assertFalse($version->remove());
        $this->assertNotNull($version->fresh());
    }

    /** @test */
    public function it_removes_a_user_made_version()
    {
        $version = Version::factory()->has(File::factory()->createdByUser())->create();
        $this->assertTrue($version->forceRemove());
        $this->assertNull($version->fresh());
    }

    /** @test */
    public function it_removes_a_version_with_dependencies()
    {
        $dependency = Version::factory()->has(File::factory())->create();
        $version = Version::factory()
            ->has(File::factory())
            ->hasAttached($dependency, ['dependency_project_id' => $dependency->versionable_id, 'type' => 0], 'dependencies_versions')
            ->create();

        $this->assertTrue($version->forceRemove());
        $this->assertNull($version->fresh());
    }

    /** @test */
    public function it_fails_to_remove_a_version_with_dependants()
    {
        $version = Version::factory()->has(File::factory())->create();
        $dependency = Version::factory()
            ->has(File::factory())
            ->hasAttached($version, ['dependency_project_id' => $version->versionable_id, 'type' => 0], 'dependencies_versions')
            ->create();

        $this->assertFalse($version->forceRemove());
        $this->assertNotNull($version->fresh());
    }

    /** @test */
    public function it_removes_a_version_but_leaves_dependency_with_a_dependant()
    {
        $dependency = Version::factory()->has(File::factory())->create();
        $vs = Version::factory()
            ->has(File::factory())
            ->hasAttached($dependency, ['dependency_project_id' => $dependency->versionable_id, 'type' => 0], 'dependencies_versions')
            ->count(2)
            ->create();
        $version = $vs->first();
        $dependant = $vs->last();

        $this->assertTrue($version->forceRemove());
        $this->assertNull($version->fresh());
        $this->assertNotNull($dependant->fresh());
    }

    /** @test */
    public function it_keeps_version_if_its_not_attached_to_a_project()
    {
        $loader = Loader::factory()
            ->has(Version::factory()->has(Library::factory()))
            ->create();
        $version = $loader->versions->first();
        $library = $version->libraries->first();

        $version->remove();
        $this->assertNotNull($version->fresh());
        $this->assertNull($library->fresh());
    }
}
