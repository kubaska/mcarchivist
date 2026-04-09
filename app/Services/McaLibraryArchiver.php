<?php

namespace App\Services;

use App\API\DTO\LibraryDTO;
use App\Enums\StorageArea;
use App\Mca\McaFile;
use App\Models\Library;
use App\Support\McaFilesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Filesystem\Path;

class McaLibraryArchiver
{
    public function __construct(
        protected McaDownloader $downloader,
        protected McaFilesystem $filesystem
    )
    {
    }

    public function archiveLibrary(LibraryDTO $library, array $mirrorList = []): Collection
    {
        Log::info("Archiving library: $library->name");

        $libraries = collect();
        $localLibrary = $this->_archiveLibrary($library, $mirrorList);
        if ($localLibrary) $libraries->push($localLibrary);
        if ($library->classifiers) {
            foreach ($library->classifiers as $classifier) {
                $libraries->merge($this->_archiveLibrary($classifier));
            }
        }

        return $libraries;
    }

    protected function _archiveLibrary(LibraryDTO $library, array $mirrorList = []): ?Library
    {
        if (! $library->basicComponent) return null;

        if ($local = Library::query()->where('name', $library->name)->first()) {
            if ($library->hasHash() && $local->hashes->isComparable($library->getHashAssoc()) && $local->hashes->differentTo($library->getHashAssoc())) {
                throw new \RuntimeException(sprintf('Library [%s] with a different hash already exists', $library->name));
            }

            return $local;
        }

        $libraryDir = $this->filesystem->getStoragePath(StorageArea::LIBRARIES, $library->getPathWithoutFile(), makeDir: true);

        if ($fileExists = $this->filesystem->exists($libraryPath = Path::join($libraryDir, $library->getFileName()))) {
            if ($library->getHash()) {
                if (hash_file($library->getHashAlgo(), $libraryPath) === $library->getHash()) {
                    Log::stack(['queue', 'stack'])->warning(
                        sprintf('Library "%s" missing in DB, but exists on disk! Readding.', $library->name)
                    );

                    return $this->saveLibrary($library, new McaFile($libraryPath));
                } else {
                    throw new \RuntimeException(sprintf('Library [%s] with a different hash already exists on disk', $library->name));
                }
            }
        }

        if ($fileExists) {
            Log::stack(['queue', 'stack'])->warning(sprintf('Overwriting library file: %s', $library->name));
        }

        $file = $this->downloader->downloadFromMirrorList(
            [...array_map(fn(string $host) => $library->getUrl($host), $mirrorList), $library->getUrl()],
            $libraryDir,
            $library->getFileName(),
            $library->getHashAlgo(),
            $library->getHash(),
            $library->getSize(),
            // At this point, we know that DB entry is missing, there is a file with the same name,
            // and we can't verify that this is the file because there is no hash.
            // This is the only instance where it's OK to overwrite the file, since library names should be unique.
            $fileExists ? ['force_overwrite' => true] : []
        );

        return $this->saveLibrary($library, $file);
    }

    protected function saveLibrary(LibraryDTO $library, McaFile $file): Library
    {
        return Library::query()->firstOrCreate(
            ['name' => $library->name],
            ['hashes' => $file->makeHashList(), 'size' => $file->getSize()]
        );
    }
}
