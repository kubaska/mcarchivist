<?php

namespace App\Jobs;

use App\Enums\JobType;
use App\Services\McaGameArchiver;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class ArchiveGameVersion extends Job
{
    use Batchable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected string $version,
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
            new WithoutOverlapping('', 10, $this->timeout)
        ];
    }

    public static function getJobType(): JobType
    {
        return JobType::ARCHIVING;
    }

    /**
     * Execute the job.
     *
     * @return int
     */
    public function handle(McaGameArchiver $gameArchiver): int
    {
        if (empty($this->components)) {
            Log::stack(['queue', 'stack'])->warning('Archive GameVersion called with no components');
            return 0;
        }

        $version = $gameArchiver->archive($this->version, $this->components);

        if ($this->requestedByUser) {
            $version->markComponentsCreatedByUser($this->components);
        }

        return 0;
    }
}
