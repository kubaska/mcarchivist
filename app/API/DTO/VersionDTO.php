<?php

namespace App\API\DTO;

use App\API\Platform\Curseforge;
use App\Enums\VersionType;
use App\Models\File;
use App\Models\GameVersion;
use App\Models\Loader;
use App\Models\Project;
use App\Models\Version;
use App\Support\Utils;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class VersionDTO extends DTO implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly string $remoteId,
        public readonly ?string $remoteProjectId,
        public readonly string $name,
        public readonly ?string $version,
        public readonly ?array $components,
        public readonly VersionType $type,
        public readonly ?string $changelog,
        public readonly int $downloads,
        public readonly Collection $files,
        public readonly array $gameVersions,
        public readonly Collection $loaders,
        public readonly Collection $dependencies,
        public readonly ?Carbon $publishedAt,
        public readonly string $platform,
        public readonly bool $local = false,
        public ?array $extra = null
    )
    {
    }

    public static function fromLocal(Version $version): VersionDTO
    {
        return new self(
            $version->id,
            $version->remote_id,
            $version->relationLoaded('versionable') ? $version->versionable?->remote_id : null,
            $version->version,
            null,
            $version->components,
            $version->type,
            $version->changelog,
            0,
            $version->relationLoaded('files')
                ? $version->files->map(fn(File $file) => FileDTO::fromLocal($file))
                : new Collection(),
            $version->relationLoaded('game_versions')
                ? $version->game_versions->map(fn(GameVersion $gv) => ['remote_id' => null, 'name' => $gv->name])->toArray()
                : [],
            $version->relationLoaded('loaders')
                ? $version->loaders->map(fn(Loader $loader) => LoaderDTO::fromLocal($loader, $loader->remotes->firstWhere('platform', $version->platform)))
                : collect(),
            $version->relationLoaded('dependencies')
                ? $version->dependencies->map(fn(Project $dependency) => new DependencyDTO(
                    $dependency->getKey(),
                    $dependency->pivot->dependency_version_id,
                    null,
                    $dependency->pivot->type
                ))
                : new Collection(),
            $version->published_at,
            $version->platform,
            true
        );
    }

    public function includesGameVersions(): bool
    {
        return ! empty($this->gameVersions);
    }

    public function getGameVersionNames(): array
    {
        return Arr::map($this->gameVersions, fn($v) => $v['name']);
    }

    public function hasLocalFiles(): bool
    {
        return $this->files->contains(fn(FileDTO $file) => $file->local);
    }

    public function hasLoader(string $name): bool
    {
        return $this->loaders->contains(fn(LoaderDTO $loader) => $loader->name === $name);
    }

    public function hasLoaderRemoteId(string $loaderRemoteId): bool
    {
        return $this->loaders->contains(fn(LoaderDTO $loader) => $loader->remoteId === $loaderRemoteId);
    }

    public function hasAnyLoader(Collection $loaders): bool
    {
        return $this->loaders->intersectUsing(
            $loaders,
            fn(LoaderDTO $x, LoaderDTO $y) => $x->name <=> $y->name
        )->isNotEmpty();
    }

    public function hasGameVersion(string $gameVersion): bool
    {
        return in_array($gameVersion, $this->getGameVersionNames());
    }

    /**
     * Determines if this version can run on any of the given game versions.
     *
     * @param array $versions
     * @return bool
     */
    public function hasAnyGameVersion(array $versions): bool
    {
        if (count($versions) === 0) return true;
        return count(array_intersect($this->getGameVersionNames(), $versions)) > 0;
    }

    /**
     * Determines if this version can run on ALL given game versions.
     *
     * @param array $versions
     * @return bool
     */
    public function hasGameVersions(array $versions): bool
    {
        return empty(array_diff($this->getGameVersionNames(), $versions));
    }

    public function getPrimaryFile(): ?FileDTO
    {
        $file = $this->files->filter(fn(FileDTO $file) => $file->primary)?->first();
        return $file ?? $this->files->first();
    }

    /**
     * Determine if this version qualifies for loader workaround.
     *
     * Before May 2021 Curseforge did not have an option to select a mod loader when publishing a new version.
     * This creates a bunch of issues, for example main mod is tagged with Forge, but its dependency is not.
     * I am giving a very generous time range here, because some modders did not get the memo,
     * or used automatic publishing tools that did not get updated to support setting the loader.
     *
     * @see https://mailchi.mp/844b51b9bdf1/whats-new-with-overwolf-curseforge-may2-edited
     * @see https://support.curseforge.com/en/support/solutions/articles/9000197242-file-project-types-and-additional-fields
     * @return bool
     */
    public function qualifiesForCurseforgeLoaderWorkaround(): bool
    {
        return $this->platform === Curseforge::id()
            && ($this->loaders->isEmpty() || ($this->loaders->containsOneItem() && $this->loaders->first()->name === 'Forge'))
            && $this->publishedAt->isBefore(Carbon::create(2021, 12, 31));
    }

    public static function fromArray(array $version): VersionDTO
    {
        return new self(
            $version['id'],
            $version['remote_id'],
            $version['remote_project_id'],
            $version['name'],
            $version['version'],
            $version['components'],
            VersionType::from($version['type']),
            $version['changelog'],
            $version['downloads'],
            collect(array_map(fn(array $f) => FileDTO::fromArray($f), $version['files'])),
            $version['game_versions'],
            collect(array_map(fn(array $l) => LoaderDTO::fromArray($l), $version['loaders'])),
            collect(array_map(fn(array $d) => DependencyDTO::fromArray($d), $version['dependencies'])),
            Carbon::make($version['published_at']),
            $version['platform']
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'remote_id' => $this->remoteId,
            'remote_project_id' => $this->remoteProjectId,
            'name' => $this->name,
            'version' => $this->version,
            'components' => $this->components ? Utils::sortComponents($this->components) : null,
            'type' => $this->type->value,
            'changelog' => $this->changelog,
            'downloads' => $this->downloads,
            'files' => $this->files,
            'game_versions' => $this->gameVersions,
            'loaders' => $this->loaders,
            'dependencies' => $this->dependencies,
            'published_at' => $this->publishedAt,
            'platform' => $this->platform,
            'local' => $this->local
        ];
    }
}
