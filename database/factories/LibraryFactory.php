<?php

namespace Database\Factories;

use App\Models\Library;
use App\Support\HashList;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LibraryFactory extends Factory
{
    protected $model = Library::class;

    public function definition(): array
    {
        return [
            'name' => sprintf('org.mcatest:%s:%s', $this->faker->unique()->word(), $this->faker->semver()),
            'hashes' => new HashList(['sha1' => Str::random(64)]),
            'size' => mt_rand(9999, 999999),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
