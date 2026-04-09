<?php

namespace Tests;

use App\API\Contracts\BaseThirdPartyApi;
use App\API\DTO\AuthorDTO;
use App\API\DTO\FileDTO;
use App\API\DTO\Modpack\ModpackInstallProfileDTO;
use App\API\DTO\VersionDTO;
use App\API\DTO\ProjectDTO;
use App\API\Requests\GetVersionsRequest;
use App\API\Requests\SearchProjectsRequest;
use App\API\ThirdPartyApiResponse;
use App\Models\Author;
use App\Models\File;
use App\Models\MasterProject;
use App\Models\Project;
use App\Models\Version;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LocalDBThirdPartyApi extends BaseThirdPartyApi
{
    public static function id(): string
    {
        return 'local';
    }

    public static function name(): string
    {
        return 'Test adapter';
    }

    public static function themeColor(): string
    {
        return '#000000';
    }

    public function search(SearchProjectsRequest|array $options): ThirdPartyApiResponse
    {
        $projects = MasterProject::query()
            ->with('preferred_project')
            ->get()
            ->map(function (MasterProject $mp) {
                return ProjectDTO::fromLocal($mp, $mp->preferred_project);
            });

        return new ThirdPartyApiResponse($projects, false);
    }

    public function getProject(string $id, array $options = []): ThirdPartyApiResponse
    {
        $mp = MasterProject::query()
            ->with('preferred_project')
            ->findOrFail($id);

        return new ThirdPartyApiResponse(ProjectDTO::fromLocal($mp, $mp->preferred_project), false);
    }

    public function getProjects(array $ids, array $options = []): ThirdPartyApiResponse
    {
        // TODO: Implement getProjects() method.
    }

    public function getProjectVersions(string $projectId, GetVersionsRequest|array $options): ThirdPartyApiResponse
    {
        $project = Project::query()->with('versions')->findOrFail($projectId);
        $versions = $project->versions->map(fn(Version $v) => VersionDTO::fromLocal($v));

        return new ThirdPartyApiResponse($versions, false);
    }

    public function getProjectDependencies(string $projectId, array $options = []): ThirdPartyApiResponse
    {
        // TODO: Implement getProjectDependencies() method.
    }

    public function getProjectDependants(string $projectId, array $options = []): ThirdPartyApiResponse
    {
        // TODO: Implement getProjectDependants() method.
    }

    public function getVersion(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse
    {
        // TODO: Implement getVersion() method.
    }

    public function getVersions(array $ids, array $options = []): ThirdPartyApiResponse
    {
        // TODO: Implement getVersions() method.
    }

    /**
     * @inheritDoc
     */
    public function getVersionsFromHashes(array $hashes, string $algorithm, array $options = []): ThirdPartyApiResponse
    {
        // TODO: Implement getVersionsFromHashes() method.
    }

    public function getVersionFiles(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse
    {
        $files = File::query()
            ->whereHas('version', fn(Builder $q) => $q->where('remote_id', $versionId))
            ->get();

        return new ThirdPartyApiResponse($files->map(fn(File $file) => FileDTO::fromLocal($file)), false);
    }

    public function getVersionDependencies(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse
    {
        // TODO: Implement getVersionDependencies() method.
    }

    public function getVersionDependants(string $projectId, string $versionId, array $options = []): ThirdPartyApiResponse
    {
        // TODO: Implement getVersionDependants() method.
    }

    public function getProjectAuthors(string $projectId): ThirdPartyApiResponse
    {
        $project = Project::query()->with('authors')->findOrFail($projectId);
        $authors = $project->authors->map(fn(Author $author) => AuthorDTO::fromLocal($author));

        return new ThirdPartyApiResponse($authors, false);
    }

    public function getCategories(): Collection
    {
        // TODO: Implement getCategories() method.
    }

    public function getLoaders(): Collection
    {
        // TODO: Implement getLoaders() method.
    }

    public function getAllProjectVersions(string $projectId, array $options = []): ThirdPartyApiResponse
    {
        // TODO: Implement getAllProjectVersions() method.
    }

    public function getProjectVersionsToDate(string $projectId, Carbon $date): Collection
    {
        // TODO: Implement getProjectVersionsToDate() method.
    }

    public function getProjectVersionsForGameVersions(string $projectId, array $gameVersions): ThirdPartyApiResponse
    {
        // TODO: Implement getProjectVersionsForGameVersions() method.
    }

    /**
     * @inheritDoc
     */
    public function isModpackFile(string $fileName): bool
    {
        // TODO: Implement isModpackFile() method.
    }

    /**
     * @inheritDoc
     */
    public function parseModpackInstallProfile(string $filePath): ModpackInstallProfileDTO|false
    {
        // TODO: Implement parseModpackInstallProfile() method.
    }
}
