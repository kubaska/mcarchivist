<?php

namespace App\API\Platform\Modrinth;

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
use App\API\Platform\Modrinth;
use App\Enums\VersionType;
use App\Support\HashList;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ModrinthResponseTransformer extends ApiResponseTransformer
{
    public static function toProjectDTO(array $project): ProjectDTO
    {
        return new ProjectDTO(
            // Search uses "project_id", getProject uses "id"
            data_get($project, 'project_id') ?? data_get($project, 'id'),
            data_get($project, 'project_id') ?? data_get($project, 'id'),
            null,
            $project['title'],
            data_get($project, 'description', ''),
            data_get($project, 'body') === null
                ? null
                : Str::sanitize(Str::markdown(data_get($project, 'body', ''), [
                    'html_input' => 'allow', // MR descriptions are a mix of markdown and html
                    'allow_unsafe_links' => false,
                    'max_nesting_level' => 20,
                    'max_delimiters_per_line' => 100
                ]
            )),
            $project['icon_url'],
            // Gallery images - search returns array of urls, getProject returns data structure
            array_map(fn($image) => [
                'title' => data_get($image, 'title'),
                'description' => data_get($image, 'description'),
                'thumbnail_url' => data_get($image, 'url'),
                'url' => data_get($image, 'raw_url')
            ], data_get($project, 'gallery', [])),
            sprintf('https://modrinth.com/%s/%s', $project['project_type'], $project['slug']),
            $project['downloads'],
            // Game Versions - search uses "versions" key, getProject uses "game_versions" key; "versions" are reserved for Modrinth version IDs
            data_get($project, 'game_versions', data_get($project, 'versions')),
            collect(array_map(fn($loader) => static::fromNameToLoaderDTO($loader), data_get($project, 'loaders', []))),
            null,
            collect(array_filter([Modrinth::getProjectType($project['project_type'])])),
            collect(array_map(fn($category) => static::fromNameToCategoryDTO($category), array_unique([
                ...data_get($project, 'categories'),
                ...data_get($project, 'additional_categories', [])
            ]))),
            Modrinth::id(),
        );
    }

    public static function toVersionDTO(array $version): VersionDTO
    {
        return new VersionDTO(
            $version['id'],
            $version['id'],
            $version['project_id'],
            $version['name'],
            $version['version_number'],
            null,
            static::toVersionType($version['version_type']),
            Str::markdown(data_get($version, 'changelog', ''), [
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 20,
                'max_delimiters_per_line' => 100
            ]),
            (int) $version['downloads'],
            collect(array_map(fn(array $v) => static::toFileDTO($v), $version['files'])),
            array_map(fn(string $gv) => ['remote_id' => $gv, 'name' => $gv], $version['game_versions']),
            collect($version['loaders'])->map(fn(string $loader) => static::fromNameToLoaderDTO($loader)),
            collect(array_map(fn($dependency) => static::toDependencyDTO($dependency), $version['dependencies'])),
            Carbon::make($version['date_published']),
            Modrinth::id()
        );
    }

    public static function toFileDTO(array $file): FileDTO
    {
        return new FileDTO(
            $file['filename'],
            $file['filename'],
            null,
            null,
            $file['filename'],
            $file['url'],
            $file['size'],
            new HashList([
                'sha1' => $file['hashes']['sha1'],
                'sha512' => $file['hashes']['sha512']
            ]),
            $file['primary'],
            false
        );
    }

    public static function toDependencyDTO(array $dependency): DependencyDTO
    {
        return new DependencyDTO(
            data_get($dependency, 'project_id'),
            data_get($dependency, 'version_id'),
            data_get($dependency, 'file_name'),
            data_get($dependency, 'dependency_type')
        );
    }

    public static function toLoaderDTO(array $loader): LoaderDTO
    {
        $projectTypes = collect($loader['supported_project_types'])
            // filter out generic project type
            ->filter(fn(string $type) => $type !== 'project');

        // Filter manually added "mod" type for datapacks and plugins.
        if ($projectTypes->contains('datapack') || $projectTypes->contains('plugin')) {
            $projectTypes = $projectTypes->filter(fn(string $type) => $type !== 'mod');
        }

        return new LoaderDTO(
            $loader['name'],
            $loader['name'],
            Modrinth::id(),
            Modrinth::formatLoaderName($loader['name']),
            $projectTypes->map(fn(string $type) => Modrinth::getProjectType($type))->values()
        );
    }

    public static function fromNameToLoaderDTO(string $name): LoaderDTO
    {
        return new LoaderDTO($name, $name, Modrinth::id(), Modrinth::formatLoaderName($name));
    }

    public static function toAuthorDTO(array $author): AuthorDTO
    {
        $username = data_get($author, 'user.username');

        return new AuthorDTO(
            data_get($author, 'user.id'),
            $username,
            data_get($author, 'role'),
            data_get($author, 'user.avatar_url'),
            'https://modrinth.com/user/'.$username,
        );
    }

    public static function toCategoryDTO(array $category): CategoryDTO
    {
        return new CategoryDTO(
            $category['name'],
            $category['name'],
            Modrinth::id(),
            Modrinth::formatCategoryName($category['name']),
            ucwords(str_replace('_', ' ', $category['header'])),
            collect(array_filter(Modrinth::getCategoryProjectTypes($category['project_type']))),
            null,
            null
        );
    }

    protected static function fromNameToCategoryDTO(string $categoryName): CategoryDTO
    {
        return new CategoryDTO(
            $categoryName,
            $categoryName,
            Modrinth::id(),
            Modrinth::formatCategoryName($categoryName),
            false,
            null,
            null,
            null
        );
    }

    public static function toPagination(array $pagination): PaginationDTO
    {
        $total = (int)$pagination['total_hits'];
        $perPage = (int)$pagination['limit'];
        $offset = (int)$pagination['offset'];

        return new PaginationDTO(
            $total,
            $perPage,
            round(($offset + $perPage) / $perPage, 0, PHP_ROUND_HALF_UP),
            max((int) ceil($total / $perPage), 1)
        );
    }

    /**
     * @see{https://support.modrinth.com/en/articles/8802351-modrinth-modpack-format-mrpack}
     * @param array $mod
     * @return ModpackModDTO
     */
    public static function toModpackModDTO(array $mod): ModpackModDTO
    {
        // Extract project ID, version ID and file ID from download URL.
        // It seems highly unlikely they'll change this, because that would mean breaking all existing mod packs.
        $cdnUrl = Arr::first(
            $mod['downloads'],
            fn(string $url) => parse_url($url, PHP_URL_HOST) === 'cdn.modrinth.com'
        );

//        if ($cdnUrl) {
//            $result = preg_match('\/data\/(\w+?)\/versions\/(.+?)\/(.+)$', $cdnUrl, $matches, PREG_UNMATCHED_AS_NULL);
//
//            if (! $result) {
//                Log::error('Modrinth mod URL did not contain a required component', ['url' => Arr::first($mod['downloads'])]);
//            }
//        } else {
//            Log::info('');
//            $matches = [null, null, null, null];
//        }

        return new ModpackModDTO(
//            $matches[1],
//            $matches[2],
//            $matches[3],
            null,
            null,
            null,
            Str::afterLast($mod['path'], '/'),
            // env fields are optional
            // value can be: required, optional, unsupported.
            data_get($mod, 'env.client', 'required') === 'required',
            data_get($mod, 'env.server', 'required') === 'required',
            $mod['path'],
            $mod['downloads'],
            new HashList($mod['hashes']),
            $mod['fileSize'],
            is_null($cdnUrl)
        );
    }

    public static function toModpackInstallProfileDTO(array $profile): ModpackInstallProfileDTO
    {
        $loaders = Arr::except($profile['dependencies'], 'minecraft');

        return new ModpackInstallProfileDTO(
            $profile['name'],
            $profile['versionId'],
            null,
            $profile['dependencies']['minecraft'],
            collect(Arr::mapWithKeys($loaders, fn(string $v, string $k) => ['name' => $k, 'version' => $k])),
            collect(array_map(fn(array $file) => static::toModpackModDTO($file), $profile['files'])),
            null,
            $profile['formatVersion']
        );
    }

    protected static function toVersionType($type): VersionType
    {
        return match ($type) {
            'release' => VersionType::RELEASE,
            'beta' => VersionType::BETA,
            'alpha' => VersionType::ALPHA,
            default => throw new \RuntimeException('Unknown mod version type: '.$type)
        };
    }
}
