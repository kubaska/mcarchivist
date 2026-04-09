<?php

namespace App\Http\Controllers;

use App\Enums\JobState;
use App\Models\JobStatus;
use App\Resources\JobStatusResource;
use App\Services\JobService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
    public function index(Request $request)
    {
        $query = JobStatus::query()
            ->leftJoin('failed_jobs', 'failed_jobs.uuid', '=', 'job_statuses.uuid')
            ->select(['job_statuses.*', 'failed_jobs.exception']);

        if ($request->has('ids')) {
            $jobs = $query->findMany((array)$request->get('ids'));
        } else {
            $jobs = $query->whereIn('state', [JobState::CREATED, JobState::RUNNING, JobState::FAILED])->get();
        }

        return JobStatusResource::collection($jobs);
    }

    public function cancel($id, JobService $jobService)
    {
        $cancelled = DB::transaction(function () use ($id, $jobService) {
            $status = JobStatus::query()->lockForUpdate()->findOrFail($id);

            if ($status->canBeCancelled()) {
                return $jobService->cancel($status);
            }

            return false;
        });

        if ($cancelled) return response(null, 204);

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
