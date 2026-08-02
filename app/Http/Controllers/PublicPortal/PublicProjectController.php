<?php

namespace App\Http\Controllers\PublicPortal;

use App\Events\PublicProjectViewed;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicPortal\PublicProjectFilterRequest;
use App\Models\Agency;
use App\Models\District;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Services\PublicPortal\PublicProjectService;
use Illuminate\View\View;

class PublicProjectController extends Controller
{
    public function index(PublicProjectFilterRequest $request, PublicProjectService $projects): View
    {
        $filters = $request->filters();

        return view('public.projects.index', [
            'projects' => $projects->listing($filters),
            'filters' => $filters,
            'agencies' => Agency::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'statuses' => ProjectStatus::query()->orderBy('name')->get(['id', 'name']),
            'districts' => District::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Project $project, PublicProjectService $projects): View
    {
        PublicProjectViewed::dispatch($project);

        return view('public.projects.show', $projects->detail($project));
    }
}
