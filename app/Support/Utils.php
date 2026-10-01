<?php

namespace App\Support;

use App\API\Requests\GetVersionsRequest;
use App\API\Requests\SearchProjectsRequest;
use App\Mca;
use Illuminate\Support\Arr;

class Utils
{
    public static array $primaryComponents = ['installer', 'universal', 'client', 'server', 'server_windows'];

    public static array $componentOrder = [
        'installer' => 0, 'universal' => 1, 'client' => 2, 'server' => 3, 'server_windows' => 4,
        'client_mappings' => 5, 'server_mappings' => 6,
        'src' => 7, 'sources' => 7,
        'changelog' => 8, 'userdev' => 9, 'mdk' => 10, 'launcher' => 11
    ];

    public static function isPrimaryComponent(string $component): bool
    {
        return in_array($component, self::$primaryComponents);
    }

    public static function sortComponents(array $components): array
    {
        uasort($components, fn(string $a, string $b) => (self::$componentOrder[$a] ?? 99) <=> (self::$componentOrder[$b] ?? 99));
        return array_values($components);
    }

    public static function isEnum($enum): bool
    {
        return $enum instanceof \UnitEnum;
    }

    public static function getEnumValue(\UnitEnum $enum, $default = null)
    {
        return match (true) {
            $enum instanceof \BackedEnum => $enum->value,
            $enum instanceof \UnitEnum => $enum->name,
            default => value($default),
        };
    }

    public static function getRequests(): array
    {
        return [
            SearchProjectsRequest::class,
            GetVersionsRequest::class
        ];
    }

    /**
     * Find common hash algo from an array of algos.
     *
     * @param array $algos 2d array of algos e.g. [['sha1'], ['sha1', 'sha256']]
     * @return string|null
     */
    public static function findCommonHashAlgo(array $algos): ?string
    {
        return Arr::first(self::findCommonHashAlgos($algos));
    }

    /**
     * Find common hash algos from an array of algos.
     *
     * @param array $algos 2d array of algos e.g. [['sha1'], ['sha1', 'sha256']]
     * @return array
     */
    public static function findCommonHashAlgos(array $algos): array
    {
        return array_intersect(Mca::FILE_HASHES_ALGOS, ...$algos);
    }

    public static function isInvalidWritableEmptyDirectory(string $path): bool|string
    {
        $filesystem = app(McaFilesystem::class);

        if (! $filesystem->exists($path)) {
            return sprintf('Directory "%s" does not exist', $path);
        }

        if (! $filesystem->isWritable($path)) {
            return sprintf('Directory "%s" is not writable', $path);
        }

        if (! $filesystem->isEmptyDirectory($path)) {
            return sprintf('Directory "%s" is not empty', $path);
        }

        return false;
    }
}
