<?php

namespace App\Jobs;

use App\Enums\JobType;
use App\Models\MasterProject;
use App\Services\McaRulesetArchiver;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ArchiveProjectFromRulesetJob extends Job implements ShouldBeUnique
{
    use Batchable;

    public function __construct(protected MasterProject $project)
    {
    }

    public function uniqueId()
    {
        return $this->project->getKey();
    }

    public function middleware(): array
    {
        return [
            ...parent::middleware(),
            new WithoutOverlapping('', 10, $this->timeout)
        ];
    }

    public static function getJobType(): JobType
    {
        return JobType::ARCHIVING;
    }

    public function handle(McaRulesetArchiver $archiver)
    {
        $archiver->archive($this->project);
    }
}
