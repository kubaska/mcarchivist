<?php

namespace App\API\Contracts;

use App\API\DTO\CategoryDTO;
use App\API\DTO\Modpack\ModpackInstallProfileDTO;
use App\API\Requests\Base\ThirdPartyApiRequest;
use App\API\Requests\GetVersionsRequest;
use App\API\Requests\SearchProjectsRequest;
use App\API\ThirdPartyApiResponse;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface ThirdPartyApi
{
    /**
     * Get a unique platform ID
     *
     * @return string
     */
    public static function id(): string;

    /**
     * Get platform name
     *
     * @return string
     */
    public static function name(): string;

    /**
     * Get platform theme color
     *
     * @return string
     */
    public static function themeColor(): string;

    /**
     * Register settings
     *
     * @param SettingsService $settings
     * @return void
     */
    public static function registerSettings(SettingsService $settings);

    /**
     * Configures the request
     *
     * @param class-string<ThirdPartyApiRequest> $request
     * @return void
     */
    public static function configureRequest(string $request);

    /**
     * Search projects
     *
     * @param SearchProjectsRequest|array $options
     * @return ThirdPartyApiResponse
     */
    public function search(SearchProjectsRequest|array $options): ThirdPartyApiResponse;

    /**
     * Get project
     *
     * @param string $id Project ID
     * @param array $options
     * @return ThirdPartyApiResponse
     */
    public function getProject(string $id, array $options = []): ThirdPartyApiResponse;

    /**
     * Get multiple projects
     *
     * @param array $ids Project IDs
     * @param array $options
     * @return ThirdPartyApiResponse
     */
    public function getProjects(array $ids, array $options = []): ThirdPartyApiResponse;

    /**
     * Get project versions
     *
     * @param string $projectId
     * @param GetVersionsRequest|array $options
     * @return ThirdPartyApiResponse
     */
    public function getProjectVersions(string $projectId, GetVersionsRequest|array $options): ThirdPartyApiResponse;

    /**
     * Get project dependencies. This function must return `ProjectDTO`s.
     *
     * @param string $projectId
     * @param array $options
     * @return ThirdPartyApiResponse
     */
    public function getProjectDependencies(string $projectId, array $options = []): ThirdPartyApiResponse;

    /**
     * Get project dependants. This function must return `ProjectDTO`s.
     *
     * @param string $projectId
     * @param array $options
     * @return ThirdPartyApiResponse
     */
    public function getProjectDependants(string $projectId, array $options = []): ThirdPartyApiResponse;

    /**
     * Get version
     *
     * @param string $projectId
     * @param string $versionId
     * @param array $options
     * @return ThirdPartyApiResponse
     */
    public function getVersion(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse;

    /**
     * Get multiple versions
     *
     * @param array $ids
     * @param array $options
     * @return ThirdPartyApiResponse
     */
    public function getVersions(array $ids, array $options = []): ThirdPartyApiResponse;

    /**
     * Get a collection of versions from provided list of hashes, with a selected hash algorithm.
     * Returned versions MUST be keyed by hash.
     *
     * @param array $hashes
     * @param string $algorithm
     * @param array $options
     * @return ThirdPartyApiResponse
     */
    public function getVersionsFromHashes(array $hashes, string $algorithm, array $options = []): ThirdPartyApiResponse;

    /**
     * Get version files.
     *
     * @param string $projectId
     * @param string $versionId
     * @param array $options
     * @return ThirdPartyApiResponse
     */
    public function getVersionFiles(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse;

    /**
     * Get version dependencies. This function must return `ProjectDTO`s.
     *
     * @param string $projectId
     * @param string $versionId
     * @param array $options
     * @return ThirdPartyApiResponse
     */
    public function getVersionDependencies(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse;

    /**
     * Get version dependants. This function must return `ProjectDTO`s.
     *
     * @param string $projectId
     * @param string $versionId
     * @param array $options
     * @return ThirdPartyApiResponse
     */
    public function getVersionDependants(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse;

    /**
     * Get project authors
     *
     * @param string $projectId
     * @return ThirdPartyApiResponse
     */
    public function getProjectAuthors(string $projectId): ThirdPartyApiResponse;

    /**
     * Get all categories
     *
     * @return Collection
     */
    public function getCategories(): Collection;

    /**
     * Get all loaders
     *
     * @return Collection
     */
    public function getLoaders(): Collection;


    /**
     * Get all project versions
     *
     * @param string $projectId
     * @param array $options
     * @return ThirdPartyApiResponse
     */
    public function getAllProjectVersions(string $projectId, array $options = []): ThirdPartyApiResponse;

    /**
     * Get all project versions that were published between now and the specified date.
     * This method does not need to do any filtering, that means returning versions outside of the selected range is fine too.
     *
     * @param string $projectId
     * @param Carbon $date
     * @return Collection
     */
    public function getProjectVersionsToDate(string $projectId, Carbon $date): Collection;

    /**
     * Get project versions for specified game versions
     *
     * @param string $projectId
     * @param array $gameVersions
     * @return ThirdPartyApiResponse
     */
    public function getProjectVersionsForGameVersions(string $projectId, array $gameVersions): ThirdPartyApiResponse;

    /**
     * Make a category tree from a flat collection, if needed.
     *
     * @param Collection<CategoryDTO> $categories
     * @return Collection<CategoryDTO>
     */
    public function makeCategoryTree(Collection $categories): Collection;

    /**
     * Loosely check if file name looks like a modpack file.
     * This method does not need to return a 100% accurate result.
     *
     * @param string $fileName File name with an extension
     * @return bool
     */
    public function isModpackFile(string $fileName): bool;

    /**
     * Parse modpack install profile from modpack file.
     *
     * @param string $filePath Absolute path to file
     * @return ModpackInstallProfileDTO|false ModpackInstallProfileDTO on success, or FALSE on failure.
     */
    public function parseModpackInstallProfile(string $filePath): ModpackInstallProfileDTO|false;
}
