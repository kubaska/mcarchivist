<?php

namespace App\Services;

use App\API\DTO\FileDTO;
use App\Enums\StorageArea;
use App\Mca\McaFile;
use App\Models\File;
use App\Models\Version;
use App\Support\McaFilesystem;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Filesystem\Path;

abstract class BaseArchiver
{
    public function __construct(protected McaDownloader $downloader, protected McaFilesystem $filesystem)
    {
    }

    /**
     * Handle archiving the file.
     *
     * @param Version $version
     * @param FileDTO $fileDTO
     * @param string $versionDir
     * @return File
     */
    public function archiveFile(Version $version, FileDTO $fileDTO, string $versionDir): File
    {
        $localFile = $version->files->first(fn(File $f) => $f->remote_id === $fileDTO->remoteId);

        $storageArea = $localFile?->storage_area ?: $version->getStorageArea();
        $versionDir = $localFile?->path ?: $versionDir;
        $versionPath = $this->filesystem->getStoragePath($storageArea, $versionDir, makeDir: true);
        $fileName = $localFile?->file_name ?: McaFilesystem::makeFileName($fileDTO->name);

        $fileExists = false;
        $shouldOverwriteFile = false;

        // Check if file already exists.
        if (file_exists($fullPath = Path::join($versionPath, $fileName))) {
            $fileExists = true;

            // File exists, check if its hash is the same.
            if ($fileDTO->hashes->isNotEmpty()) {
                [$algo, $hash] = $fileDTO->hashes->getFirstHash();

                if (hash_file($algo, $fullPath) === $hash) {
                    if ($localFile) {
                        return $this->updateFile($localFile, $fileDTO);
                    } else {
                        $file = new McaFile(Path::join($versionPath, $fileName));

                        return $this->saveFile($version, $fileDTO, $file, $storageArea, $versionDir, $fileName);
                    }
                }
            }
        }

        [$algo, $hash] = $fileDTO->hashes->getFirstHash();
        $file = $this->downloader->downloadToTemporaryDirectory(
            $fileDTO->url,
            $fileName,
            $algo,
            $hash,
            $fileDTO->size
        );

        if ($fileExists) {
            $mcaFileOnDisk = new McaFile($fullPath);

            // Compare hashes between downloaded file and file on disk.
            if ($mcaFileOnDisk->getHash('sha512') === $file->getHash('sha512')) {
                $this->filesystem->delete($file);

                if ($localFile) {
                    return $this->updateFile($localFile, $fileDTO);
                } else {
                    return $this->saveFile($version, $fileDTO, $mcaFileOnDisk, $storageArea, $versionDir, $fileName);
                }
            } else {
                $fileOnDiskBoundToAnotherModel = File::query()
                    ->when($localFile, fn(Builder $q) => $q->whereNot('id', $localFile->getKey()))
                    ->where('storage_area', $storageArea)
                    ->where('path', $versionDir)
                    ->where('file_name', $fileName)
                    ->exists();

                if ($fileOnDiskBoundToAnotherModel) {
                    // File on disk is used by another model, generate a new file name for this file.
                    $fileName = McaFilesystem::makeUniqueFileName($versionPath, $fileDTO->name);
                } else {
                    // File on disk is unused by us, overwrite it
                    $shouldOverwriteFile = true;
                }
            }
        }

        if ($localFile === null && $fileExists === false) {
            // Look for duplicates
            $fileHashList = $file->makeHashList($fileDTO->hashes->all());

            $fileWithSameHashAndSize = File::query()
                ->whereHas('version', fn(Builder $q) => $q->forMorph($version->versionable_type))
                ->whereSha512($fileHashList->get('sha512'))
                ->where('size', $fileDTO->size ?: $file->getSize())
                ->get()
                ->filter(fn(File $duplicate) => $fileHashList->compareTo($duplicate->hashes))
                ->first();

            // File with the same hash already exists.
            if ($fileWithSameHashAndSize) {
                $duplicate = $fileWithSameHashAndSize->first();

                $this->filesystem->delete($file);

                return $this->saveFile(
                    $version,
                    $fileDTO,
                    new McaFile($duplicate->getAbsoluteFilePath()),
                    $duplicate->storage_area,
                    $duplicate->path,
                    $duplicate->file_name
                );
            }
        }

        $this->filesystem->ensureDirectoryExists($versionPath);

        // Move file to destination
        $this->filesystem->move($file, $fullPath = Path::join($versionPath, $fileName), $shouldOverwriteFile);

        if ($localFile) {
            return $this->updateFile($localFile, $fileDTO);
        } else {
            return $this->saveFile($version, $fileDTO, new McaFile($fullPath), $storageArea, $versionDir, $fileName);
        }
    }

    private function updateFile(File $file, FileDTO $fileDTO): File
    {
        $file->fill([
            'side' => $fileDTO->side,
            'original_file_name' => $fileDTO->name,
            'primary' => $fileDTO->primary
        ])->save();

        return $file;
    }

    private function saveFile(Version $version, FileDTO $fileDTO, McaFile $fileOnDisk, StorageArea $storageArea, string $path, string $fileName): File
    {
        return $version->files()->firstOrCreate(
            ['remote_id' => $fileDTO->remoteId],
            [
                'component' => $fileDTO->component,
                'side' => $fileDTO->side,
                'storage_area' => $storageArea,
                'path' => $path,
                'file_name' => $fileName,
                'original_file_name' => $fileDTO->name,
                'hashes' => $fileOnDisk->makeHashList($fileDTO->hashes->all()),
                'size' => $fileDTO->size ?: $fileOnDisk->getSize(),
                'primary' => $fileDTO->primary
            ]
        );
    }
}
