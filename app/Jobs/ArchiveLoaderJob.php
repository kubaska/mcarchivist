<?php

namespace App\Jobs;

use App\Enums\JobType;
use App\Models\Version;
use App\Services\McaLoaderArchiver;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ArchiveLoaderJob extends Job
{
    use Batchable;

    public function __construct(
        protected Version $version,
        protected array $components,
        protected bool $requestedByUser,
        protected array $options = []
    )
    {
    }

    public function middleware(): array
    {
        return [
            ...parent::middleware(),
            new WithoutOverlapping($this->version->versionable->getKey(), 10, $this->timeout)
        ];
    }

    public static function getJobType(): JobType
    {
        return JobType::ARCHIVING;
    }

    public function handle(McaLoaderArchiver $archiver): int
    {
        $archiver->archiveVersion($this->version, $this->components);

        if ($this->requestedByUser) {
            $this->version->markComponentsCreatedByUser($this->components);
        }

        return 0;
    }
}
