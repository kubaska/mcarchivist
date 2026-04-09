<?php

namespace App\Jobs;

use App\Enums\JobType;
use App\Jobs\Middleware\McaWithoutOverlapping;
use App\Models\Loader;
use App\Services\McaLoaderArchiver;

class UpdateLoaderIndexJob extends Job
{
    public function __construct(protected Loader $loader, protected bool $revalidate = false)
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

    public function handle(McaLoaderArchiver $archiver)
    {
        $archiver->updateIndex($this->loader, true);

        dispatch_now(new UpdateLoaderReleaseDatesJob($this->loader));
    }
}
