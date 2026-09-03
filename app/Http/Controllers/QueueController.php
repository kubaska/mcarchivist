<?php

namespace App\Http\Controllers;

use App\Enums\JobState;
use App\Models\JobStatus;
use App\Resources\JobStatusResource;
use App\Services\JobService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
    public function index(Request $request)
    {
        if ($request->has('ids')) {
            $jobs = JobStatus::query()->withException()->findMany($request->array('ids'));
        } else {
            $jobs = collect([
                JobStatus::query()->whereIn('state', [JobState::CREATED, JobState::RUNNING])->get(),
                JobStatus::query()->withException()->where('state', JobState::FAILED)->limit(20)->get()
            ])->flatten(1)->sortBy('created_at');
        }

        return JobStatusResource::collection($jobs);
    }

    public function previous(Request $request)
    {
        $tasks = JobStatus::query()
            ->withException()
            ->whereIn('state', [JobState::CANCELLED, JobState::FAILED, JobState::FINISHED])
            ->when($request->input('exclude'), fn(Builder $q) => $q->whereNotIn('job_statuses.id', $request->array('exclude')))
            ->when($request->input('cursor'), fn(Builder $q) => $q->where('job_statuses.id', '<', (int)$request->input('cursor')))
            ->limit(20)
            ->latest('job_statuses.id')
            ->get();

        return JobStatusResource::collection($tasks);
    }

    public function cancel($id, JobService $jobService)
    {
        /** @var JobStatus $status */
        $status = DB::transaction(function () use ($id, $jobService) {
            $status = JobStatus::query()->findOrFail($id);

            if ($status->canBeCancelled() && $jobService->cancel($status)) {
                return $status;
            }

            return false;
        });

        if ($status) return new JobStatusResource($status->refresh());

        return response()->json([
            'error' => 'Unable to cancel job',
            'description' => 'This job cannot be cancelled, as it is already being processed or did already finish.'
        ], 422);
    }

    public function retry($id)
    {
        $status = JobStatus::query()->findOrFail($id);

        if ($status->state === JobState::FAILED && $status->uuid) {
            Artisan::call('queue:retry '.$status->uuid);

            $status->state = JobState::CREATED;
            $status->save();

            return new JobStatusResource($status);
        }

        return response()->json([
            'error' => 'Could not retry job',
            'description' => 'The given job did not fail, or is missing UUID'
        ], 400);
    }
}
