<?php

namespace App\Http\Controllers\Admin\Intelligence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Intelligence\StoreProcessingJobRequest;
use App\Models\IntelligenceProcessingJob;
use App\Services\Intelligence\ProcessingJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntelligenceProcessingJobController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('viewAny', IntelligenceProcessingJob::class) === true, 403);

        return view('admin.intelligence.processing-jobs.index', [
            'jobs' => IntelligenceProcessingJob::query()->latest('queued_at')->paginate(15)->withQueryString(),
        ]);
    }

    public function store(StoreProcessingJobRequest $request, ProcessingJobService $jobs): RedirectResponse
    {
        $jobs->queue($request->target(), $request->jobType(), $request->user());

        return back()->with('status', 'Processing preparation job queued.');
    }
}
