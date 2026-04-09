<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'remote_id' => fn(array $attributes) => Str::slug($attributes['name']),
            'platform' => 'local',
            'parent_category_id' => null,
            'name' => $this->faker->word(),
            'group' => 'Categories',
            'merge_with_id' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
