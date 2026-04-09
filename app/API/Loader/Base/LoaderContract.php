<?php

namespace App\API\Loader\Base;

use App\API\DTO\FileDTO;
use App\API\DTO\LoaderInstallProfileDTO;
use App\API\DTO\LoaderVersionDTO;
use App\Mca\McaFile;
use App\Models\Version;
use Illuminate\Support\Collection;

interface LoaderContract
{
    /**
     * ID of the loader.
     *
     * @return string
     */
    public static function id(): string;

    /**
     * Name of the loader.
     *
     * @return string
     */
    public static function name(): string;

    /**
     * Determines if loader versions are versioned by game versions.
     * For example, Forge would return true, because every version needs to be installed alongside specific game version.
     * Fabric Loader works with every game version which means it's not.
     *
     * @return bool
     */
    public function isVersionedByGameVersions(): bool;

    /**
     * Returns an array of mirror download servers.
     *
     * @return array<string>
     */
    public function getMirrorList(): array;

    /**
     * Get a list of files in the specified version.
     *
     * @param string $version
     * @param array $options
     * @return Collection<FileDTO>
     */
    public function getVersion(string $version, array $options = []): Collection;

    /**
     * Get a list of loader versions.
     *
     * @param array $options
     * @return Collection<LoaderVersionDTO>
     */
    public function getVersions(array $options = []): Collection;

    /**
     * Get version install manifest.
     *
     * @param string $version
     * @param Collection<McaFile> $files
     * @return LoaderInstallProfileDTO|false LoaderInstallProfileDTO on success, FALSE on failure.
     */
    public function getVersionManifest(string $version, Collection $files): LoaderInstallProfileDTO|false;

    /**
     * Get release dates for a collection of Versions.
     * Returned array must be in following format: VersionID => ReleaseDate
     *
     * @param Collection<Version> $versions
     * @return array
     */
    public function getReleaseDates(Collection $versions): array;
}
