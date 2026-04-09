<?php

namespace App\Services;

use App\Enums\JobState;
use App\Jobs\Job;
use App\Logging\QueueLogHandler;
use App\Models\JobStatus;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JobService
{
    public function dispatch(Job $job, string $name, ?string $frontendId = null): JobStatus
    {
        return DB::transaction(function () use ($job, $name, $frontendId) {
            $id = app(Dispatcher::class)->dispatch($job);

            // On testing, jobs are not actually dispatched
            if (App::environment() === 'testing') {
                $id = mt_rand(10000, 10000000);
            }

            if (is_null($id)) {
                throw new \RuntimeException(
                    sprintf('Failed to dispatch Job %s: missing ID', get_class($job)),
                    compact('name', 'frontendId')
                );
            }

            return JobStatus::query()->create([
                'original_id' => $id,
                'frontend_id' => $frontendId,
                'job_type' => method_exists($job, 'getJobType') ? $job::getJobType() : null,
                'state' => 0,
                'name' => $name
            ]);
        });
    }

    public function cancel(JobStatus $status): bool
    {
        return DB::transaction(function () use ($status) {
            $status = JobStatus::query()
                ->lockForUpdate()
                ->where('id', $status->id)
                ->where('state', $status->state)
                ->first();

            if ($status->state === JobState::FAILED && $status->uuid) {
                DB::table('failed_jobs')->where('uuid', $status->uuid)->delete();
            }

            return $status->update(['state' => JobState::CANCELLED]);
        });
    }

    public static function onBeforeQueueEvent(JobProcessing $event)
    {
        if (is_null($event->job->getJobId()) && is_null($event->job->uuid())) {
            return;
        }

        DB::transaction(function () use ($event) {
            $status = JobStatus::query()->lockForUpdate()->when(
                $event->job->attempts() === 1,
                fn($q) => $q->where('original_id', $event->job->getJobId()),
                fn($q) => $q->where('uuid', $event->job->uuid())
            )->first();

            if (! $status) {
                return;
            }

            if ($status->state === JobState::CANCELLED) {
                $event->job->delete();
                return;
            }

            // Update job status and fill UUID if missing
            $status->fill(array_merge(
                ['state' => JobState::RUNNING],
                (is_null($status->uuid) && $event->job->uuid()) ? ['uuid' => $event->job->uuid()] : []
            ))->save();
        });

        self::getQueueLogHandler()?->reset();
    }

    public static function onAfterQueueEvent(JobProcessed $event)
    {
        DB::transaction(function () use ($event) {
            self::updateJobState($event->job->uuid(), JobState::FINISHED);
        });
    }

    public static function onFailedQueueEvent(JobFailed $event)
    {
        DB::transaction(function () use ($event) {
            $status = self::updateJobState($event->job->uuid(), JobState::FAILED);

            if ($status && $messages = self::getQueueLogHandler()?->getMessages()) {
                $status->fill(['details' => Arr::join($messages, PHP_EOL)])->save();
                self::getQueueLogHandler()?->reset();
            }
        });
    }

    public static function onBatchCreated(Batch $batch)
    {
        if ($batch->totalJobs === 0) {
            $batch->delete();
        }

        JobStatus::query()->create([
            'original_id' => null,
            'batch_id' => $batch->id,
            'frontend_id' => null,
            'job_type' => null,
            'state' => 0,
            'name' => $batch->name."\nScheduled automatic archive"
        ]);
    }

    public static function onBatchProgress(Batch $batch)
    {
        DB::transaction(function () use ($batch) {
            self::updateBatchState($batch->id, JobState::RUNNING);
        });
    }

    public static function onBatchCompleted(Batch $batch)
    {
        DB::transaction(function () use ($batch) {
            self::updateBatchState($batch->id, JobState::FINISHED);
        });
    }

    public static function cancelBatchByName(string $name)
    {
        DB::transaction(function () use ($name) {
            $batches = DB::table('job_batches')
                ->lockForUpdate()
                ->where('name', $name)
                ->whereNull('cancelled_at')
                ->get(['id']);

            foreach ($batches as $batch) {
                Bus::findBatch($batch->id)?->cancel();
            }

            self::updateBatchState(Arr::pluck($batches, 'id'), JobState::FINISHED);
        });
    }

    /**
     * Updates Job state.
     * Should run in transaction.
     *
     * @param string|null $uuid
     * @param JobState $state
     * @return JobStatus|null
     */
    private static function updateJobState(?string $uuid, JobState $state): ?JobStatus
    {
        if (is_null($uuid)) {
            return null;
        }

        $status = JobStatus::query()->where('uuid', $uuid)->lockForUpdate()->first();
        $status?->fill(['state' => $state])->save();
        return $status;
    }

    private static function updateBatchState(array|string $batchId, JobState $state)
    {
        JobStatus::query()->lockForUpdate()->whereIn('batch_id', Arr::wrap($batchId))->update(['state' => $state]);
    }

    private static function getQueueLogHandler(): ?QueueLogHandler
    {
        $handlers = Log::channel('queue')->getLogger()->getHandlers();

        return Arr::first($handlers, fn(object $handler) => get_class($handler) === QueueLogHandler::class);
    }
}
