<?php

namespace App\API\DTO\Modpack;

use App\API\DTO\DTO;
use Illuminate\Support\Collection;

class ModpackInstallProfileDTO extends DTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly ?string $author,
        public readonly string $gameVersion,
        public readonly Collection $loaders,
        public readonly Collection $mods,
        public readonly ?string $overridesDir,
        public readonly int $manifestVersion
    )
    {
    }
}
