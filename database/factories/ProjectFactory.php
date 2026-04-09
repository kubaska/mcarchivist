<?php

namespace Database\Factories;

use App\Models\MasterProject;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    protected array $exampleProjectNames = [
        'Applied Energistics', 'Just Enough Items', 'Mouse Tweaks', 'Create', 'The Twilight Forest'
    ];

    public function definition(): array
    {
        return [
            'platform' => 'local',
            'remote_id' => fn(array $attributes) => Str::slug($attributes['name']),
            'name' => $this->faker->unique()->randomElement($this->exampleProjectNames),
            'downloads' => mt_rand(0, 999999),
            'project_url' => fn(array $attributes) => 'https://mods.net/mods/'.Str::slug($attributes['name']),
            'logo' => '',
            'summary' => '',
            'description' => fn(array $attributes) => sprintf('A test %s mod.', $attributes['name']),
            'last_version_check' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'master_project_id' => fn(array $attributes) => MasterProject::factory(null, ['name' => $attributes['name']]),
        ];
    }

    public function configure(): ProjectFactory
    {
        return $this
            ->afterCreating(function (Project $project) {
                if (! $project->master_project->preferred_project_id) {
                    $project->master_project->preferred_project_id = $project->id;
                    $project->master_project->save();
                }
            });
    }
}
