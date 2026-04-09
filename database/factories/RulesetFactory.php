<?php

namespace Database\Factories;

use App\Models\Ruleset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class RulesetFactory extends Factory
{
    protected $model = Ruleset::class;

    public function definition(): array
    {
        return [
            'name' => 'An Example Ruleset',
            'custom' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
