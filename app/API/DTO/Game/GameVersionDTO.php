<?php

namespace App\API\DTO\Game;

use App\API\DTO\DTO;
use App\Enums\VersionType;
use Carbon\Carbon;

class GameVersionDTO extends DTO
{
    public function __construct(
        public readonly string $name,
        public readonly VersionType $type,
        public readonly string $url,
        public readonly string $time,
        public readonly string $releaseTime,
        public readonly string $hash,
    )
    {
    }

    public function getTime(): Carbon
    {
        return Carbon::make($this->time);
    }

    public function getReleaseTime(): Carbon
    {
        return Carbon::make($this->releaseTime);
    }

    public static function fromMojang(array $version): GameVersionDTO
    {
        return new self(
            $version['id'],
            VersionType::fromMojang($version['type']),
            $version['url'],
            $version['time'],
            $version['releaseTime'],
            $version['sha1'],
        );
    }
}
