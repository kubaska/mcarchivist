<?php

namespace App\Models;

use App\Casts\AsHashListCast;
use App\Enums\FileSide;
use App\Enums\StorageArea;
use App\Support\McaFilesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class File extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'side' => FileSide::class,
        'storage_area' => StorageArea::class,
        'hashes' => AsHashListCast::class,
        'primary' => 'boolean'
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

    /**
     * Add a where clause on `hashes` column that extracts Sha512 hash from it and compares it with provided hash.
     *
     * @param Builder $query
     * @param string $sha512
     */
    public function scopeWhereSha512(Builder $query, string $sha512)
    {
        $compileInstr = fn(string $haystack, string $needle) => $this->getConnection()->getDriverName() === 'pgsql'
            ? "STRPOS($haystack, '$needle')"
            : "INSTR($haystack, '$needle')";

        $hash = 'sha512'.AsHashListCast::getHashSeparator();
        $hashIdentifierLength = strlen($hash);
        $hashLength = 128;

        $query->where(function (Builder $q) use ($compileInstr, $hash, $hashIdentifierLength, $hashLength, $sha512) {
            $q->whereRaw(DB::raw($compileInstr('hashes', $hash).' != 0')) // only models that contain sha512
                ->whereRaw(DB::raw(sprintf(
                    "SUBSTRING(files.hashes, %s + %s, %s) = ?",
                    $compileInstr('files.hashes', $hash),
                    $hashIdentifierLength,
                    $hashLength
                )), [$sha512]);
        });
    }

    public function getFullPathAttribute(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.$this->file_name;
    }

    /**
     * Returns an absolute path to directory where the file is located.
     *
     * @return string
     */
    public function getAbsoluteDirectoryPath(): string
    {
        return (app(McaFilesystem::class))->getStoragePath($this->storage_area, $this->path);
    }

    /**
     * Returns an absolute path to file.
     *
     * @return string
     */
    public function getAbsoluteFilePath(): string
    {
        return (app(McaFilesystem::class))->getStoragePath($this->storage_area, $this->full_path);
    }

    public function existsOnDisk(): bool
    {
        return is_file($this->getAbsoluteFilePath());
    }

    public function validateHash(): bool
    {
        if (! $this->existsOnDisk()) return false;
        [$algo, $hash] = $this->hashes->getFirstHash();
        return $hash === hash_file($algo, $this->getAbsoluteFilePath());
    }

    public function remove(bool $force = false): bool
    {
        if ($this->created_by !== null && $force === false) {
            return false;
        }

        DB::transaction(function () {
            $fs = app(McaFilesystem::class);
            $filePath = $fs->getStoragePath($this->storage_area, $this->full_path);

            $isFileUsedByOtherModel = static::query()
                ->whereNot('id', $this->getKey())
                ->where('storage_area', $this->storage_area)
                ->where('path', $this->path)
                ->where('file_name', $this->file_name)
                ->exists();

            $this->delete();

            if (! $isFileUsedByOtherModel) {
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
                        $fs->getStoragePath($this->storage_area, $this->path),
                        $fs->getStoragePath($this->storage_area)
                    );
                } catch (\Exception $e) {
                    Log::error('Failed to clean up empty directories', [$e]);
                }
            }
        });

        return true;
    }

    public function forceRemove(): bool
    {
        return $this->remove(true);
    }
}
