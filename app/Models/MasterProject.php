<?php

namespace App\Models;

use App\Support\McaFilesystem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class MasterProject extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Relations

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function preferred_project()
    {
        return $this->belongsTo(Project::class);
    }

    public function versions()
    {
        return ($this->hasManyThrough(Version::class, Project::class, secondKey: 'versionable_id')
            ->where('versionable_type', Project::class)
        );
    }

    public function mergeProject(MasterProject $projectToMerge)
    {
        $projectToMerge->load('projects');

        \DB::transaction(function () use ($projectToMerge) {
            $projectToMerge->projects->map(fn(Project $p) => $p->update(['master_project_id' => $this->getKey()]));
            $projectToMerge->delete();
        });
    }

    public function unmergeProject(Project $project)
    {
        \DB::transaction(function () use ($project) {
            $newMP = $this->create([
                'name' => $project->name,
                'preferred_project_id' => $project->getKey(),
                'archive_dir' => self::generateArchiveDir($project->name)
            ]);

            $project->update(['master_project_id' => $newMP->getKey()]);
        });
    }

    /**
     * Generates a unique directory name where the files will be stored.
     *
     * @param string $name
     * @return string
     */
    public static function generateArchiveDir(string $name): string
    {
        $dir = null;
        for ($tries = 0; $tries <= 100; $tries++) {
            $i = $tries / 100;

            // First iteration will try the name as is
            // 1 - 70 will try with 4 extra random chars
            // 71 - 100 will gradually step up to 8 chars
            $exists = static::query()
                ->where('archive_dir', $testDir = McaFilesystem::makeDirName(
                    $name,
                    randomChars: $tries === 0 ? 0 : (Number::clamp((int)(($i * $i) * 10), 4, 8))
                ))
                ->exists();

            if (! $exists) {
                $dir = $testDir;
                break;
            }
        }

        if (! $dir) {
            throw new \RuntimeException('Exceeded maximum amount of tries generating project dir name');
        }

        return $dir;
    }
}
