<?php

namespace App\Jobs;

use App\Enums\DependencyQualifier;
use App\Enums\EProjectType;
use App\Enums\JobType;
use App\Models\File;
use App\Models\ProjectType;
use App\Models\Version;
use App\Services\McaArchiver;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class RevalidateVersionJob extends Job
{
    public function __construct(protected Version $version)
    {
    }

    public function middleware(): array
    {
        return [
            ...parent::middleware(),
            new WithoutOverlapping($this->version->getKey(), 10, $this->timeout)
        ];
    }

    public static function getJobType(): JobType
    {
        return JobType::REVALIDATING;
    }

    public function handle(McaArchiver $archiver)
    {
        $version = $archiver->archiveProjectFile(
            $this->version->versionable,
            $this->version->remote_id,
            $this->version->files->map(fn(File $f) => $f->remote_id)->toArray(),
            DependencyQualifier::REQUIRED_ONLY,
            true
        );

        if ($version->versionable->project_types->contains(fn(ProjectType $type) => $type->type === EProjectType::MODPACK)) {
            dispatch_now(new ArchiveModpack($this->version->versionable, $version, true, true));
        }
    }
}
