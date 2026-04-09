<?php

namespace App\Models;

use App\Casts\AsHashListCast;
use App\Enums\FileSide;
use App\Enums\StorageArea;
use App\Support\McaFilesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class File extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'side' => FileSide::class,
        'hashes' => AsHashListCast::class
    ];

    public function version()
    {
        return $this->belongsTo(Version::class);
    }

    public function isCreatedByUser(): bool
    {
        return $this->created_by !== null;
    }

    public function scopeCreatedByUser(Builder $query): Builder
    {
        return $query->whereNotNull('created_by');
    }

    public function getFullPathAttribute(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.$this->file_name;
    }

    /**
     * Returns an absolute path to directory where the file is located.
     *
     * @param StorageArea $storageArea
     * @return string
     */
    public function getAbsoluteDirectoryPath(StorageArea $storageArea): string
    {
        return (app(McaFilesystem::class))->getStoragePath($storageArea, $this->path);
    }

    /**
     * Returns an absolute path to file.
     *
     * @param StorageArea $storageArea
     * @return string
     */
    public function getAbsoluteFilePath(StorageArea $storageArea): string
    {
        return (app(McaFilesystem::class))->getStoragePath($storageArea, $this->full_path);
    }

    public function existsOnDisk(StorageArea $storageArea): bool
    {
        return is_file($this->getAbsoluteFilePath($storageArea));
    }

    public function validateHash(StorageArea $storageArea): bool
    {
        if (! $this->existsOnDisk($storageArea)) return false;
        [$algo, $hash] = $this->hashes->getFirstHash();
        return $hash === hash_file($algo, $this->getAbsoluteFilePath($storageArea));
    }

    public function remove(StorageArea $storageArea, bool $force = false): bool
    {
        if ($this->created_by !== null && $force === false) {
            return false;
        }

        DB::transaction(function () use ($storageArea) {
            $fs = app(McaFilesystem::class);
            $filePath = $fs->getStoragePath($storageArea, $this->full_path);

            $this->delete();

            if ($fs->exists($filePath)) {
                if ($fs->delete($filePath)) {
                    Log::info('Deleted file: '.$filePath);
                } else {
                    Log::error('Failed to remove file: '.$filePath);
                }
            } else {
                Log::warning(sprintf('Failed to remove file: %s (File is missing).', $filePath));
            }

            try {
                $fs->cleanupEmptyDirectories(
                    $fs->getStoragePath($storageArea, $this->path),
                    $fs->getStoragePath($storageArea)
                );
            } catch (\Exception $e) {
                Log::error('Failed to clean up empty directories', [$e]);
            }
        });

        return true;
    }

    public function forceRemove(StorageArea $storageArea): bool
    {
        return $this->remove($storageArea, true);
    }
}
