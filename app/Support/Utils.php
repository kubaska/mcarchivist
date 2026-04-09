<?php

namespace App\Support;

use App\API\DTO\FileDTO;
use App\API\Requests\GetVersionsRequest;
use App\API\Requests\SearchProjectsRequest;
use App\Mca;
use Illuminate\Support\Arr;
use Symfony\Component\Filesystem\Path;

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

    public static function getRequests(): array
    {
        return [
            SearchProjectsRequest::class,
            GetVersionsRequest::class
        ];
    }

    /**
     * Determine if remote file exists locally by comparing hash. Makes a file name.
     *
     * @param string $path
     * @param FileDTO $remote
     * @return array<bool, string>
     */
    public static function verifyFileAlreadyExistsAndMakeFileName(string $path, FileDTO $remote): array
    {
        $fileName = McaFilesystem::makeFileName($remote->name);

        // File does not exist, return default file name
        if (! file_exists($fullPath = Path::join($path, $fileName))) {
            return [false, $fileName];
        }

        // File with default name exists, but there is no hash to verify if it's the file we want.
        // We never want to overwrite user files, so generate a unique name and play it safe.
        if ($remote->hashes->isEmpty()) {
            return [false, McaFilesystem::makeUniqueFileName($path, $remote->name)];
        }

        // File exists, and we verified it's the same as remote.
        [$algo, $hash] = $remote->hashes->getFirstHash();
        if (hash_file($algo, $fullPath) === $hash) {
            return [true, $fileName];
        }

        // File exists, but hash differs. Make a unique name so we don't overwrite user files.
        return [false, McaFilesystem::makeUniqueFileName($path, $remote->name)];
    }

    public static function verifyAssetExists(string $path, string $fileName, string $sha1): bool
    {
        // File does not exist
        if (! file_exists($fullPath = Path::join($path, $fileName))) {
            return false;
        }

        // File exists, and we verified it's the same as remote.
        if (hash_file('sha1', $fullPath) === $sha1) {
            return true;
        }

        // File exists, but hash differs. We should force redownload the asset.
        return false;
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
}
