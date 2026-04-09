<?php

namespace App\API\Platform\Curseforge;

use App\API\ApiResponseTransformer;
use App\API\DTO\AuthorDTO;
use App\API\DTO\CategoryDTO;
use App\API\DTO\DependencyDTO;
use App\API\DTO\FileDTO;
use App\API\DTO\LoaderDTO;
use App\API\DTO\Modpack\ModpackInstallProfileDTO;
use App\API\DTO\Modpack\ModpackModDTO;
use App\API\DTO\PaginationDTO;
use App\API\DTO\ProjectDTO;
use App\API\DTO\VersionDTO;
use App\API\Platform\Curseforge;
use App\Enums\VersionType;
use App\Support\HashList;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class CurseforgeResponseTransformer extends ApiResponseTransformer
{
    public static function toProjectDTO(array $project, ?string $description = null): ProjectDTO
    {
        $logo = empty(data_get($project, 'logo.thumbnailUrl'))
            ? data_get($project, 'logo.url')
            : data_get($project, 'logo.thumbnailUrl');

        return new ProjectDTO(
            data_get($project, 'id'),
            data_get($project, 'id'),
            null,
            data_get($project, 'name'),
            data_get($project, 'summary'),
            $description === null ? null : Str::sanitize(
                $description,
                fn(HtmlSanitizerConfig $config) => $config->withAttributeSanitizer(new CurseforgeLinkoutTransformer())
            ),
            $logo,
            array_map(fn($image) => [
                'title' => data_get($image, 'title'),
                'description' => data_get($image, 'description'),
                'thumbnail_url' => data_get($image, 'thumbnailUrl'),
                'url' => data_get($image, 'url')
            ], data_get($project, 'screenshots')),
            data_get($project, 'links.websiteUrl'),
            (int)data_get($project, 'downloadCount'),
            null,
            null,
            collect(array_map(fn($author) => static::toAuthorDTO($author), data_get($project, 'authors'))),
            collect([Curseforge::primaryCategoryIdToProjectType(data_get($project, 'classId'))]),
            collect(array_map(fn($category) => static::toCategoryDTO($category), data_get($project, 'categories'))),
            Curseforge::id(),
        );
    }

    public static function toVersionDTO(array $version, ?string $changelog = null): VersionDTO
    {
        // Game versions are lumped in with loaders, so we filter them out
        $gameVersions = collect(array_filter(data_get($version, 'sortableGameVersions', []), fn($v) => $v['gameVersion']))
            ->map(fn(array $v) => [
                // "gameVersion" is missing for mod loaders, fall back to "gameVersionName"
                // we also can't reliably filter by "gameVersionTypeId", because all older game versions have it set to 1
                'remote_id' => data_get($v, 'gameVersion', data_get($v, 'gameVersionName')),
                'name' => $v['gameVersionName']]
            )
            ->values();

        return new VersionDTO(
            data_get($version, 'id'),
            data_get($version, 'id'),
            null, // Curseforge does not provide associated project ID to versions
            data_get($version, 'displayName'),
            null,
            null,
            static::toVersionType(data_get($version, 'releaseType')),
            $changelog,
            (int) data_get($version, 'downloadCount'),
            // "parentProjectId" field only exists on extra files.
            collect([static::toFileDTO($version, is_null(data_get($version, 'parentProjectId')))]),
            $gameVersions->toArray(),
            // loaders
            collect(array_reduce(data_get($version, 'gameVersions'), function ($carry, $i) {
                if (Curseforge::isModLoader($i)) $carry[] = static::toLoaderDTO($i, $i);
                return $carry;
            }, [])),
            collect(array_map(fn($dependency) => static::toDependencyDTO($dependency), data_get($version, 'dependencies', []))),
            Carbon::make(data_get($version, 'fileDate')),
            Curseforge::id()
        );
    }

    public static function toFileDTO(array $file, $primary = false): FileDTO
    {
        return new FileDTO(
            data_get($file, 'id'),
            data_get($file, 'id'),
            null,
            null,
            data_get($file, 'fileName'),
            data_get($file, 'downloadUrl'),
            data_get($file, 'fileLength'),
            new HashList(array_reduce(data_get($file, 'hashes', []), function ($a, $i) {
                $a[Curseforge::getCurseforgeHashAlgo($i['algo'])] = $i['value'];
                return $a;
            }, [])),
            $primary,
            false
        );
    }

    public static function toDependencyDTO(array $dependency): DependencyDTO
    {
        return new DependencyDTO(
            $dependency['modId'],
            null,
            null,
            Curseforge::getFileRelationType($dependency['relationType'])
        );
    }

    public static function toLoaderDTO(string $id, string $name, ?Collection $projectTypes = null): LoaderDTO
    {
        return new LoaderDTO($id, $id, Curseforge::id(), $name, $projectTypes);
    }

    public static function toAuthorDTO(array $author): AuthorDTO
    {
        return new AuthorDTO(
            data_get($author, 'id'),
            data_get($author, 'name'),
            null,
            data_get($author, 'avatarUrl'),
            data_get($author, 'url'),
        );
    }

    public static function toCategoryDTO(array $category): CategoryDTO
    {
        return new CategoryDTO(
            data_get($category, 'id'),
            data_get($category, 'id'),
            Curseforge::id(),
            data_get($category, 'name'),
            null,
            collect(array_filter([Curseforge::primaryCategoryIdToProjectType(data_get($category, 'classId'), null)])),
            data_get($category, 'parentCategoryId'),
            null
        );
    }

    public static function toPagination(array $pagination): PaginationDTO
    {
        $total = (int)data_get($pagination, 'totalCount');
        $perPage = (int)data_get($pagination, 'pageSize');
        // index = offset
        $index = (int)data_get($pagination, 'index');

        return new PaginationDTO(
            $total,
            $perPage,
            round(($index + $perPage) / $perPage, 0, PHP_ROUND_HALF_UP),
            max((int) ceil($total / $perPage), 1),
        );
    }

    public static function toModpackModDTO(array $mod): ModpackModDTO
    {
        return new ModpackModDTO(
            $mod['projectID'],
            $mod['fileID'],
            $mod['fileID'],
            null,
            data_get($mod, 'required', true),
            data_get($mod, 'required', true),
            null,
            null,
            new HashList([]),
            null,
            false
        );
    }

    public static function toModpackInstallProfileDTO(array $profile): ModpackInstallProfileDTO
    {
        return new ModpackInstallProfileDTO(
            $profile['name'],
            $profile['version'],
            $profile['author'],
            $profile['minecraft']['version'],
            collect(array_map(function (array $loader) {
                $split = explode('-', $loader['id'], 2);
                return count($split) === 2
                    ? ['name' => $split[0], 'version' => $split[1]]
                    : ['name' => $loader['id'], 'version' => null];
            }, $profile['minecraft']['modLoaders'])),
            collect(array_map(fn(array $file) => static::toModpackModDTO($file), $profile['files'])),
            $profile['overrides'],
            $profile['manifestVersion']
        );
    }

    public static function toVersionType($type): VersionType
    {
        return match ((int)$type) {
            1 => VersionType::RELEASE,
            2 => VersionType::BETA,
            3 => VersionType::ALPHA,
            default => throw new \RuntimeException('Unknown mod version type: '.$type)
        };
    }
}
