<?php

namespace App\Jobs;

use App\Enums\DependencyQualifier;
use App\Enums\EProjectType;
use App\Enums\JobType;
use App\Models\ProjectType;
use App\Services\McaArchiver;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ArchiveProject extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected string $platform,
        protected string $id,
        protected string $versionId,
        protected array $fileIds,
        protected bool $requestedByUser
    )
    {
    }

    public function middleware(): array
    {
        return [
            ...parent::middleware(),
            new WithoutOverlapping($this->platform, 10, $this->timeout)
        ];
    }

    public static function getJobType(): JobType
    {
        return JobType::ARCHIVING;
    }

    /**
     * Execute the job.
     *
     * @param McaArchiver $archiver
     * @return int
     */
    public function handle(McaArchiver $archiver): int
    {
        $project = $archiver->archiveProject($this->platform, $this->id);

        $version = $archiver->archiveProjectFile(
            $project,
            $this->versionId,
            $this->fileIds,
            DependencyQualifier::REQUIRED_ONLY
        );

        if ($project->project_types->contains(fn(ProjectType $type) => $type->type === EProjectType::MODPACK)) {
            dispatch_now(new ArchiveModpack($project, $version, $this->requestedByUser));
        }

        if ($this->requestedByUser) {
            $version->markFilesCreatedByUser($this->fileIds);
        }

        return 0;
    }
}
