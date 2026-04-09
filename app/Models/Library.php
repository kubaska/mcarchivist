<?php

namespace App\Models;

use App\Casts\AsHashListCast;
use App\Enums\StorageArea;
use App\Mca\MavenArtifact;
use App\Support\McaFilesystem;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Library extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'hash', 'hashes', 'size'
    ];

    protected $casts = [
        'hashes' => AsHashListCast::class
    ];

    public function versions()
    {
        return $this->belongsToMany(Version::class, 'library_dependencies');
    }

    public function getMavenArtifact(): MavenArtifact
    {
        return new MavenArtifact($this->name);
    }

    public function hasDependants(array|Collection|null $except = null): bool
    {
        return DB::table('library_dependencies as d')
            ->select('d.*')
            ->where('library_id', $this->getKey())
            ->when($except, function (Builder $q) use ($except) {
                $q->whereNot(function(Builder $q) use ($except) {
                    foreach ((array) $except as $i) {
                        $q->orWhere([
                            ['dependable_type', '=', Model::getActualClassNameForMorph(get_class($i))],
                            ['dependable_id', '=', $i->getKey()]
                        ]);
                    }
                });
            })
            ->exists();
    }

    public function remove(): bool
    {
        if ($this->hasDependants()) {
            return false;
        }

        DB::transaction(function () {
            $artifact = $this->getMavenArtifact();
            $fs = app(McaFilesystem::class);
            $filePath = $fs->getStoragePath(StorageArea::LIBRARIES, $artifact->path());

            $this->delete();

            if ($fs->exists($filePath)) {
                if ($fs->delete($filePath)) {
                    Log::info('Deleted library: '.$artifact->path());
                } else {
                    throw new \RuntimeException(sprintf('Failed to remove library file: %s', $artifact->path()));
                }
            } else {
                Log::warning(sprintf('Failed to remove library file: %s (File is missing). Continuing', $artifact->path()));
            }

            $fs->cleanupEmptyDirectories(
                $fs->getStoragePath(StorageArea::LIBRARIES, $artifact->pathWithoutFile()),
                $fs->getStoragePath(StorageArea::LIBRARIES)
            );
        });

        return true;
    }
}
