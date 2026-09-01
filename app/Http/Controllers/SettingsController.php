<?php

namespace App\Http\Controllers;

use App\Jobs\ChangeStorageDirectoryJob;
use App\Resources\JobStatusResource;
use App\Services\JobService;
use App\Services\SettingsService;
use App\Support\McaFilesystem;
use App\Support\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class SettingsController extends Controller
{
    // How many files will be displayed when browsing for a directory. Rest will be omitted
    private const FILE_DISPLAY_AMOUNT = 10;

    public function index(SettingsService $settings)
    {
        return $settings->getAll();
    }

    public function store(JobService $jobService, McaFilesystem $filesystem, SettingsService $settings, Request $request)
    {
        list($pathSettings, $otherSettings) = Arr::partition(
            $request->all(),
            fn($_, $key) => str_starts_with($key, 'general.storage.') && $settings->has($key)
        );

        $queuedTasks = [];

        foreach ($pathSettings as $key => $value) {
            if ($reason = Utils::isInvalidWritableEmptyDirectory($value)) {
                return response([
                    'error' => $reason,
                    'description' => 'Please select a different directory and try again.'
                ], 422);
            }
        }

        foreach ($pathSettings as $key => $value) {
            $currentPath = $settings->get($key);

            if ((! $filesystem->isDirectory($currentPath)) ||
                ($filesystem->isReadable($currentPath) && $filesystem->isEmptyDirectory($currentPath)))
            {
                // Current path does not exist, or is an empty directory, skip dispatching job and update path immediately
                $otherSettings[$key] = $value;
            } else {
                $queuedTasks[] = $jobService->dispatch(
                    new ChangeStorageDirectoryJob($key, $value),
                    "Changing storage directory\n".ucfirst($settings->getMcaSetting($key)->getName()),
                    'settings_'.$key
                );
            }
        }

        $settings->save($otherSettings);

        return empty($queuedTasks)
            ? response()->json(null, 204)
            : JobStatusResource::collection($queuedTasks);
    }

    public function directorySelector(Request $request)
    {
        $currentDir = $request->input('dir', storage_path());

        if (Path::isRelative($currentDir)) {
            return response()->json(['error' => 'Path is relative', 'description' => 'Relative paths are disallowed'], 422);
        }
        if (! is_dir($currentDir)) {
            return response()->json(['error' => 'Path does not exist', 'description' => 'Provided directory does not exist'], 422);
        }

        if ($traverseDir = $request->get('traverse')) {
            if ($traverseDir === '..') {
                $currentDir = Path::join($currentDir, '..');
            } else {
                // todo grep the traversed folder instead of looking for it manually
                $storage = iterator_to_array(Finder::create()->depth(0)->in($currentDir)->directories()->sortByName(), false);

                if (array_filter($storage, fn(SplFileInfo $v) => $traverseDir === $v->getRelativePathname())) {
                    $currentDir = Path::join($currentDir, $request->get('traverse'));
                } else {
                    return response()->json(['error' => 'Path does not exist', 'description' => 'Provided directory does not exist'], 422);
                }
            }
        }

        $storageIterator = new \DirectoryIterator($currentDir);
        $storage = [];
        $files = 0;
        /** @var \DirectoryIterator $entry */
        foreach ($storageIterator as $entry) {
            if ($entry->isDot() && $entry->getBasename() !== '..') continue;
            if ($entry->isFile()) {
                $files++;
                if ($files > self::FILE_DISPLAY_AMOUNT) continue;
            }

            $storage[] = [
                'dir' => $entry->getBasename(),
                'path' => $entry->getRealPath(),
                'type' => $entry->isDir() ? 'dir' : 'file',
                'modified_at' => $entry->getMTime()
            ];
        }

        $storage = collect($storage)
            ->sortBy([['type', 'asc'], ['dir', 'asc']])
            ->values()
            ->when($files > self::FILE_DISPLAY_AMOUNT, fn(Collection $c) => $c->push([
                'dir' => sprintf('... and %s more files', $files - self::FILE_DISPLAY_AMOUNT),
                'path' => '',
                'type' => 'file',
                'modified_at' => null
            ]));

        return [
            'dir' => $currentDir,
            'storage' => $storage
        ];
    }
}
