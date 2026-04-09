<?php

namespace App\Models;

use App\API\DTO\ProjectDTO;
use App\Support\McaFilesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'last_version_check' => 'datetime'
    ];

    // Relations

    public function master_project()
    {
        return $this->belongsTo(MasterProject::class);
    }

    public function authors()
    {
        return $this->belongsToMany(Author::class, 'authors_projects')->withPivot(['role']);
    }

    public function archive_rules()
    {
        return $this->morphMany(ArchiveRule::class, 'ruleable');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'categories_projects');
    }

    public function project_types()
    {
        return $this->belongsToMany(ProjectType::class);
    }

    public function versions()
    {
        return $this->morphMany(Version::class, 'versionable');
    }

    public function scopeGetRemote(Builder $query, string $platform, string $remoteId): Builder
    {
        return $query->where('platform', $platform)->where('remote_id', $remoteId);
    }

    public static function saveProject(ProjectDTO $projectDTO): Project
    {
        $project = static::query()
            ->where('platform', $projectDTO->platform)
            ->where('remote_id', $projectDTO->id)
            ->first();

        $updateData = [
            'downloads' => $projectDTO->downloads,
            'project_url' => $projectDTO->projectUrl,
            'logo' => $projectDTO->logo,
            'summary' => $projectDTO->summary,
            'description' => Str::limit($projectDTO->description, 65000, '')
        ];

        \DB::transaction(function () use (&$project, $projectDTO, $updateData) {
            if ($project) {
                $project->update($updateData);
            }
            else {
                $mp = MasterProject::query()->create([
                    'name' => $projectDTO->name,
                    'archive_dir' => MasterProject::generateArchiveDir($projectDTO->name)
                ]);

                $project = $mp->projects()->create([
                    ...$updateData,
                    'platform' => $projectDTO->platform,
                    'remote_id' => $projectDTO->id,
                    'name' => $projectDTO->name
                ]);

                $mp->update(['preferred_project_id' => $project->getKey()]);
            }
        });

        return $project;
    }
}
