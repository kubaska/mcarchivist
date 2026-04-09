<?php

namespace App\Jobs;

use App\Enums\JobType;
use App\Jobs\Middleware\McaWithoutOverlapping;
use App\Services\McaGameArchiver;

class UpdateGameVersionsIndexJob extends Job
{
    public function __construct(protected bool $revalidate = false)
    {
    }

    public function middleware(): array
    {
        return [
            ...parent::middleware(),
            new McaWithoutOverlapping('', 10, $this->timeout)
        ];
    }

    public static function getJobType(): JobType
    {
        return JobType::UPDATING_INDEX;
    }

    public function handle(McaGameArchiver $archiver)
    {
        $archiver->importGameVersions($this->revalidate);

        dispatch_now(new UpdateGameVersionsComponents($this->revalidate));
    }
}
