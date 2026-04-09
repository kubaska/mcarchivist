<?php

namespace Database\Factories;

use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class GameVersionFactory extends Factory
{
    protected $model = GameVersion::class;

    protected array $exampleVersionNames = ['1.6.4', '1.7.2', '1.7.10', '1.9.2', '1.12', '1.12.2', '1.16.5', '1.19.3', '1.20.1'];

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement($this->exampleVersionNames),
            'type' => 0,
            'official' => true,
            'released_at' => Carbon::now()->subYears(mt_rand(1, 10))->subMonths(mt_rand(1, 12))->subDays(mt_rand(1, 30)),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
