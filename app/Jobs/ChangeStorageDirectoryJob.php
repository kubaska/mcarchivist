<?php

namespace App\Jobs;

use App\Enums\JobType;
use App\Jobs\Middleware\Exclusive;
use App\Services\SettingsService;
use App\Support\McaFilesystem;
use App\Support\Utils;

class ChangeStorageDirectoryJob extends Job
{
    public function __construct(
        protected string $settingKey,
        protected string $newDirectory
    )
    {
    }

    public static function getJobType(): JobType
    {
        return JobType::EXCLUSIVE;
    }

    public function handle(SettingsService $settings, McaFilesystem $filesystem)
    {
        if (Exclusive::isAnyJobRunning($this->job->uuid())) {
            $this->release(10);
            return 1;
        }

        if ($reason = Utils::isInvalidWritableEmptyDirectory($this->newDirectory)) {
            $this->fail($reason);
        }

        $currentDirectory = $settings->get($this->settingKey);

        $filesystem->moveRecursive($currentDirectory, $this->newDirectory);

        $settings->save([$this->settingKey => $this->newDirectory]);

        return 0;
    }
}
