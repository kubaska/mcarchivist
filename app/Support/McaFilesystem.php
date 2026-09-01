<?php

namespace App\Support;

use App\Enums\StorageArea;
use App\Services\SettingsService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class McaFilesystem extends Filesystem
{
    public function __construct(
        protected SettingsService $settings,
        protected SymfonyFilesystem $symfonyFilesystem
    )
    {
    }

    /**
     * Move a file or directory to a new location.
     *
     * @param string $path
     * @param string $target
     * @return bool
     */
    public function move($path, $target): bool
    {
        // Let OS handle the move - PHP built-in fails when source and target destination live on different partitions.
        if (! defined('PHP_WINDOWS_VERSION_BUILD')) {
            $p = new Process(['mv', $path, $target], timeout: null);
            $p->run();

            if ($p->getExitCode() === 0) return true;
        }

        // Try built-in and if it fails, copy the source to destination and remove source.
        $this->symfonyFilesystem->rename($path, $target);
        return true;
    }

    /**
     * Recursively move a file or directory to a new location.
     *
     * @param string $path
     * @param string $target
     */
    public function moveRecursive(string $path, string $target)
    {
        $path = rtrim($path, '/\\');
        $target = rtrim($target, '/\\');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        $movedEntries = [];

        /** @var \SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->getPathname() === $target || $entry->getRealPath() === $target || isset($movedEntries[$entry->getRealPath()])) {
                continue;
            }

            $entryTargetDir = $target.substr($entry->getPath(), strlen($path));
            $entryTargetPath = $target.substr($entry->getPathname(), strlen($path));
            $movedEntries[$entryTargetPath] = true;

            if (is_link($entry)) {
                // ...
            } elseif ($entry->getType() === 'dir') {
                $this->ensureDirectoryExists($entryTargetPath);

                if ($this->isEmptyDirectory($entry->getPathname())) {
                    $this->deleteDirectory($entry->getPathname());
                }
            } elseif ($entry->getType() === 'file') {
                $this->ensureDirectoryExists($entryTargetDir);
                $this->move($entry, $entryTargetPath);
            }
        }
    }

    public function getStoragePath(StorageArea $storageArea, array|string|null $path = null, bool $makeDir = false): string
    {
        $fullPath = $this->settings->getPath($storageArea->value);

        if ($path) {
            $fullPath = Path::join($fullPath, ...Arr::wrap($path));
        }

        if ($makeDir) {
            $this->ensureDirectoryExists($fullPath);
        }

        return $fullPath;
    }

    public static function makeFileName(string $name, ?string $extra = null, int $limit = 100): string
    {
        $newName = Path::getFilenameWithoutExtension($name);
        $extension = Path::getExtension($name, true);

        if ($extension === '') {
            throw new \RuntimeException('Given file name is invalid: '.$name);
        }

        $limit = max(1, ($limit - (strlen($extension) + 1) - ($extra ? (strlen($extra) + 1) : 0)));
        // replace all non-basic characters
        $newName = preg_replace('/[^\w]/', '_', $newName);

        $newName = self::normalize(Str::limit(self::normalize($newName), $limit, ''));
        return $newName.($extra ? ('_'.$extra) : '').'.'.$extension;
    }

    public static function makeUniqueFileName(string $path, string $name, int $limit = 100): string
    {
        for ($tries = 0; $tries <= 100; $tries++) {
            $safeFileName = self::makeFileName($name, $tries === 0 ? null : Str::random(4), $limit);

            if (! file_exists(Path::join($path, $safeFileName))) {
                return $safeFileName;
            }
        }

        throw new \RuntimeException(sprintf(
            'Exhausted number of tries attempting to generate a file name in %s.',
            $path
        ));
    }

    public static function makeDirName(string $name, bool $extendCharset = false, int $randomChars = 0, int $limit = 100): string
    {
        $name = self::sanitize($name);

        $name = preg_replace($extendCharset ? '/[^\w.-]/' : '/[^\w]/', '_', $name);

        $name = self::normalize(
            Str::limit(self::normalize($name), $randomChars ? ($limit - $randomChars - 1) : $limit, '')
        );

        if ($randomChars) {
            $name = self::normalize($name.'_'.Str::random($randomChars));
        }

        return $name;
    }

    protected static function sanitize(string $name): string
    {
        // Prevent directory traversal
        while(str_contains($name, '..')) {
            $name = str_replace('..', '.', $name);
        }

        return $name;
    }

    protected static function normalize(string $name): string
    {
        while(str_contains($name, '__')) {
            $name = str_replace('__', '_', $name);
        }

        return trim($name, '_');
    }

    /**
     * Backtracks from a given directory and removes them if they're empty.
     *
     * @param string $dir Target directory
     * @param string $limitDir
     */
    public function cleanupEmptyDirectories(string $dir, string $limitDir)
    {
        $dir = Path::canonicalize($dir);
        $limitDir = Path::canonicalize($limitDir);

        if (! Path::isBasePath($limitDir, $dir)) {
            throw new \RuntimeException(sprintf('Path [%s] is not base path to [%s].', $limitDir, $dir));
        }

        while (($dir !== $limitDir) && str_contains($dir, $limitDir)) {
            if (! is_dir($dir)) return;
            if (! $this->isEmptyDirectory($dir)) return;
            Log::info('Removing empty directory: '.$dir);
            if (! rmdir($dir)) {
                Log::error('Failed to remove empty directory: '.$dir);
                return;
            }

            $dir = Path::join($dir, '..');
        }
    }
}
