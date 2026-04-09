<?php

namespace App\API\DTO;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class LoaderInstallProfileDTO extends DTO
{
    /**
     * @param string $version
     * @param string|null $gameVersion
     * @param Carbon|null $releasedAt
     * @param Collection|LibraryDTO[] $libraries
     */
    public function __construct(
        public readonly string $version,
        public readonly ?string $gameVersion,
        public readonly ?Carbon $releasedAt,
        public readonly Collection $libraries
    )
    {
    }

    /**
     * Parse a Forge install manifest
     *
     * @param array $profile
     * @param array $excludedLibraries array of regex patterns
     * @return LoaderInstallProfileDTO
     */
    public static function fromForge(array $profile, array $excludedLibraries = []): LoaderInstallProfileDTO
    {
        if (isset($profile['versionInfo'])) return self::fromForgeV1($profile, $excludedLibraries);
        else return self::fromForgeV2($profile, $excludedLibraries);
    }

    public static function fromForgeV1(array $profile, array $excludedLibraries = []): LoaderInstallProfileDTO
    {
        return new self(
            $profile['install']['version'],
            $profile['install']['minecraft'],
            Carbon::make($profile['versionInfo']['time']),
            collect(array_map(fn($lib) => LibraryDTO::fromForgeV1($lib), $profile['versionInfo']['libraries']))
                ->filter(fn(LibraryDTO $library) => !self::shouldExcludeLibrary($library, $excludedLibraries))
        );
    }

    public static function fromForgeV2(array $profile, array $excludedLibraries = []): LoaderInstallProfileDTO
    {
        return new self(
            $profile['version'],
            $profile['minecraft'],
            null,
            collect(array_map(fn($lib) => LibraryDTO::fromMojang($lib), $profile['libraries']))
                ->filter(fn(LibraryDTO $library) => !self::shouldExcludeLibrary($library, $excludedLibraries))
        );
    }

    public static function fromNeoforge(array $profile, array $excludedLibraries = []): LoaderInstallProfileDTO
    {
        return static::fromForgeV2($profile, $excludedLibraries);
    }

    public static function fromFabric(string $version, ?Carbon $releaseTime, array $manifest): LoaderInstallProfileDTO
    {
        $allLibraries = [];
        foreach ($manifest['libraries'] as $side => $libraries) {
            foreach ($libraries as $library) {
                $allLibraries[] = LibraryDTO::fromFabric($library);
            }
        }

        return new self($version, null, $releaseTime, collect($allLibraries));
    }

    protected static function shouldExcludeLibrary(LibraryDTO $library, array $excludeList): bool
    {
        foreach ($excludeList as $exclusion) {
            if (preg_match($exclusion, $library->name)) {
                Log::debug('Excluding library: '.$library->name);
                return true;
            }
        }

        return false;
    }
}
