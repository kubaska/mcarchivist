<?php

namespace Database\Factories;

use App\Models\GameVersion;
use App\Models\Project;
use App\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VersionFactory extends Factory
{
    protected $model = Version::class;

    protected static int $remoteId = 500;

    public function definition(): array
    {
        return [
            'versionable_id' => Project::factory(),
            'versionable_type' => Model::getActualClassNameForMorph(Project::class),
            'platform' => 'local',
            'remote_id' => ++static::$remoteId,
            'version' => $this->faker->unique()->semver(true, true),
            'components' => null,
            'type' => 0,
            'changelog' => null,
            'published_at' => Carbon::now()->subDays(mt_rand(1, 14))->subMinutes(mt_rand(1, 60))->subSeconds(mt_rand(1, 60)),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(fn(Version $version) => $version->fill(['id' => $version->remote_id]));
    }

    public function forGameVersions(array|Collection $gameVersions): static
    {
        return $this->afterMaking(fn(Version $version) =>
            $version->setRelation('game_versions', $gameVersions instanceof Collection ? $gameVersions : collect($gameVersions))
        );
    }

    public function forGameVersionsStr(array|string $gameVersions): static
    {
        $gameVersions = Arr::wrap($gameVersions);
        $gv = GameVersion::query()->whereIn('name', $gameVersions)->get();

        // Warn if we're missing game versions, they might have been not migrated
        if ($gv->count() !== count($gameVersions)) throw new \RuntimeException('Missing game versions');

        return $this->forGameVersions($gv);
    }
}
