<?php

namespace Database\Factories;

use App\Models\MasterProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class MasterProjectFactory extends Factory
{
    protected $model = MasterProject::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'archive_dir' => '_test_project_'.$this->faker->unique()->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'preferred_project_id' => null,
        ];
    }
}
