<?php

namespace Tests\Unit\Models;

use App\Models\MasterProject;
use Tests\Laravel\RefreshDatabase;
use Tests\TestCase;

class MasterProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_projects()
    {
        $mp1 = MasterProject::factory()->create(['name' => 'JEI', 'archive_dir' => 'JEI']);
        $mp2 = MasterProject::factory()->create(['name' => 'JEI', 'archive_dir' => 'JEI_abcd']);

        $mp1->mergeProject($mp2);

        $this->assertNull($mp2->fresh());
        $this->assertSame('JEI', $mp1->fresh()->archive_dir);
    }

    public function test_merge_projects_preserving_original_dir_name()
    {
        $mp1 = MasterProject::factory()->create(['name' => 'JEI', 'archive_dir' => 'JEI']);
        $mp2 = MasterProject::factory()->create(['name' => 'JEI', 'archive_dir' => 'JEI_abcd']);

        $mp2->mergeProject($mp1);

        $this->assertNull($mp1->fresh());
        $this->assertSame('JEI', $mp2->fresh()->archive_dir);
    }
}
