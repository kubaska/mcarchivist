<?php

namespace App\Services;

use App\Enums\VersionType;
use App\Models\GameVersion;
use Illuminate\Support\Collection;

class GameVersionService
{
    protected Collection $gameVersions;

    public function getVersionsBetween(string $from, ?string $to, bool $includeSnapshots): Collection
    {
        $allVersions = $this->getGameVersions();

        if (! $includeSnapshots) {
            $allVersions = $allVersions->filter(fn(GameVersion $gv) => $gv->type !== VersionType::SNAPSHOT);
        }

        if ($from === '*') return $allVersions;

        $vFrom = $allVersions->firstWhere('name', $from);

        if (is_null($to)) return collect([$vFrom]);

        if ($to === '*') {
            $vTo = $allVersions->sortByDesc('released_at')->first();
        } else {
            $vTo = $allVersions->firstWhere('name', $to);
        }

        if (! $vFrom) throw new \RuntimeException('No such version: '.$from);
        if (! $vTo) throw new \RuntimeException('No such version: '.$to);

        return $allVersions->whereBetween('id', [$vFrom->id, $vTo->id]);
    }

    protected function getGameVersions(): Collection
    {
        if (isset($this->gameVersions)) return $this->gameVersions;

        $this->gameVersions = GameVersion::orderBy('released_at')->get();
        return $this->gameVersions;
    }
}
