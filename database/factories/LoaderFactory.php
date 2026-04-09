<?php

namespace Database\Factories;

use App\Models\Loader;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LoaderFactory extends Factory
{
    protected $model = Loader::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'slug' => fn(array $attributes) => Str::slug($attributes['name']),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
