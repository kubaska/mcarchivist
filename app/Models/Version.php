<?php

namespace App\Models;

use App\Enums\StorageArea;
use App\Enums\VersionType;
use App\Enums\ProjectDependencyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Version extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'components' => 'array',
        'type' => VersionType::class,
        'published_at' => 'datetime'
    ];

    public function versionable()
    {
        return $this->morphTo();
    }

    public function game_versions()
    {
        return $this->belongsToMany(GameVersion::class);
    }

    public function loaders()
    {
        return $this->belongsToMany(Loader::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function libraries()
    {
        return $this->belongsToMany(Library::class, 'library_dependencies');
    }

    public function dependencies()
    {
        // We force distinct on result, because version can have multiple another versions of the same project that depend on it.
        return $this->belongsToMany(Project::class, 'dependencies', relatedPivotKey: 'dependency_project_id')
            ->withPivot(['dependency_version_id', 'type'])
            ->distinct();
    }

    public function dependencies_versions()
    {
        return $this->belongsToMany(static::class, 'dependencies', relatedPivotKey: 'dependency_version_id')
            ->withPivot(['type']);
    }

    public function dependants_versions()
    {
        return $this->belongsToMany(static::class, 'dependencies', 'dependency_version_id', 'version_id')
            ->withPivot(['type']);
    }

    /**
     * Adds where clause for given morph model.
     *
     * @param Builder $query
     * @param class-string|Model $model
     * @return Builder
     */
    public function scopeForMorph(Builder $query, Model|string $model): Builder
    {
        if ($model instanceof Model) {
            return $query
                ->where('versionable_id', $model->getKey())
                ->where('versionable_type', Model::getActualClassNameForMorph(get_class($model)));
        } else {
            return $query->where('versionable_type', Model::getActualClassNameForMorph($model));
        }
    }

    public function scopeFindRemoteOrFail(Builder $query, string $platform, string $remoteId)
    {
        return $query
            ->where('platform', $platform)
            ->where('remote_id', $remoteId)
            ->firstOrFail();
    }

    /**
     * Determines if provided components contain all the current version components.
     *
     * @param array|Collection $components
     * @return bool
     */
    public function hasAllComponents(array|Collection $components): bool
    {
        if ($components instanceof Collection) $components = $components->toArray();

        return empty(array_diff($this->components ?? [], $components));
    }

    public function markFilesCreatedByUser(array $fileIds): int
    {
        return $this->files()
            ->when(! in_array('*', $fileIds), fn(Builder $q) => $q->whereIn('remote_id', $fileIds))
            ->update(['created_by' => 1]);
    }

    public function saveAvailableComponentList(array $components): bool
    {
        $this->components = $components;
        if ($this->isDirty()) return $this->save();
        return true;
    }

    public function markComponentsCreatedByUser(array $components): int
    {
        return $this->files()
            ->when(! in_array('*', $components), fn(Builder $q) => $q->whereIn('component', $components))
            ->update(['created_by' => 1]);
    }

    public function getStorageArea(): StorageArea
    {
        return match ($this->versionable_type) {
            GameVersion::class => StorageArea::GAME,
            Loader::class => StorageArea::LOADERS,
            Project::class => StorageArea::PROJECTS,
            default => throw new \RuntimeException('Invalid file group: '.$this->versionable_type)
        };
    }

    public function addDependency(Project $project, ?Version $version, ProjectDependencyType $type)
    {
        $this->dependencies()->syncWithPivotValues(
            [$project->getKey()],
            ['dependency_version_id' => $version?->getKey(), 'type' => $type],
            false
        );
    }

    public function dependsOnAnything(): Collection|false
    {
        $this->load('dependencies_versions');

        $dependencies = $this->dependencies_versions->filter(fn(Version $d) => $d->getKey() !== $this->getKey());

        return $dependencies->isEmpty() ? false : $dependencies;
    }

    public function remove(bool $force = false): bool
    {
        return $this->doRemove($force);
    }

    public function forceRemove(): bool
    {
        return $this->doRemove(true);
    }

    private function doRemove(bool $force, ?int $parentDependencyId = null): bool
    {
        $this->load(['dependants_versions', 'dependencies_versions', 'files', 'libraries']);

        if ($force === false && $this->files->contains(fn(File $f) => $f->isCreatedByUser())) {
            return false;
        }

        // Dependants
        if ($this->dependants_versions
            ->when($parentDependencyId, fn(Collection $c) => $c->filter(fn(self $v) => $v->getKey() !== $parentDependencyId))
            ->isNotEmpty())
        {
            return false;
        }

        // Libraries
        foreach ($this->libraries as $library) {
            DB::tryTransaction(function () use ($library) {
                $this->libraries()->detach([$library->getKey()]);
                $library->remove();
            }, fn(\Throwable $e) => Log::error('Removing version failed', [$e]));
        }

        // Dependencies
        $dependencies = $this->fetchDependenciesForDeletion($this->dependencies_versions, $this->getKey());
        /** @var Version $dependency */
        foreach ($dependencies as $dependency) {
            $dependency->doRemove($force, $this->getKey());
        }

        // Files
        foreach ($this->files as $file) {
            Log::info('Deleting file: '.$file->file_name);
            $file->remove($this->getStorageArea(), $force);
        }

        // Drop loaders
        $this->loaders()->detach();

        // Drop the version itself only if it's attached to a project
        if ($this->versionable_type === Project::class) {
            Log::info('Deleting version: '.$this->version);
            $this->game_versions()->detach();
            $this->delete();
        }

        return true;
    }

    /**
     * @param Collection $dependencies List of dependencies to check.
     * @param int $parentVersionId Parent version ID; used to exclude it from dependant list.
     * @param array $dependencyIds Internal; protects against circular dependencies.
     * @return array
     */
    private function fetchDependenciesForDeletion(Collection $dependencies, int $parentVersionId, array $dependencyIds = []): array
    {
        $result = [];

        /** @var Version $dependency */
        foreach ($dependencies as $dependency) {
            $dependency->load(['dependants_versions', 'dependencies_versions']);

            // Protect against circular dependencies
            if (in_array($dependency->getKey(), $dependencyIds)) {
                Log::warning(sprintf(
                    'Skipping circular dependency: %s %s [%s]',
                    $dependency->versionable->name, $dependency->version, $dependency->getKey()
                ));
                continue;
            }

            // Some other version depends on this file. Do not stage for deletion
            if ($dependency->dependants_versions->filter(fn(Version $v) => $v->getKey() !== $parentVersionId)->isNotEmpty())
                continue;

            // Stage for deletion
            $result[$dependency->getKey()] = $dependency;

            // This version depends on some other project.
            if ($dependency->dependencies_versions->isNotEmpty()) {
                $childDeps = $this->fetchDependenciesForDeletion($dependency->dependencies_versions, $dependency->getKey(), array_keys($result));
                $result = array_merge($childDeps, $result);
            }
        }

        return array_values($result);
    }
}
