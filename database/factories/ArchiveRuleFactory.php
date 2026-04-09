<?php

namespace Database\Factories;

use App\Enums\DependencyQualifier;
use App\Models\ArchiveRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ArchiveRuleFactory extends Factory
{
    protected $model = ArchiveRule::class;

    public function definition(): array
    {
        return [
            'game_version_from' => '*',
            'game_version_to' => null,
            'with_snapshots' => false,
            'release_type' => null,
            'release_type_priority' => false,
            'count' => 1,
            'sorting' => 0,
            'dependencies' => DependencyQualifier::NONE->value,
            'all_files' => false,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    public function forAllGameVersions(bool $withSnapshots = false): Factory
    {
        return $this->state(fn(array $attributes) => [
            'game_version_from' => '*', 'game_version_to' => null, 'with_snapshots' => $withSnapshots
        ]);
    }

    public function forGameVersion(string $from, ?string $to = null, bool $withSnapshots = false): Factory
    {
        return $this->state(fn(array $attributes) => [
            'game_version_from' => $from, 'game_version_to' => $to, 'with_snapshots' => $withSnapshots
        ]);
    }

    public function withReleasePriority(): Factory
    {
        return $this->state(fn(array $attributes) => ['release_type_priority' => true]);
    }

    public function withoutDependencies(): Factory
    {
        return $this->state(fn(array $attributes) => ['dependencies' => DependencyQualifier::NONE->value]);
    }

    public function withRequiredDependencies(): Factory
    {
        return $this->state(fn(array $attributes) => ['dependencies' => DependencyQualifier::REQUIRED_ONLY->value]);
    }

    public function withAllDependencies(): Factory
    {
        return $this->state(fn(array $attributes) => ['dependencies' => DependencyQualifier::ALL->value]);
    }

    public function sortedByNewest(): Factory
    {
        return $this->state(fn(array $attributes) => ['sorting' => 0]);
    }

    public function sortedByOldest(): Factory
    {
        return $this->state(fn(array $attributes) => ['sorting' => 1]);
    }
}
