<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProjectRequest;
use App\Models\Agency;
use App\Models\Country;
use App\Models\FiscalYear;
use App\Models\FundingSource;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectPriority;
use App\Models\ProjectStatus;
use App\Services\Projects\ProjectLifecycleService;
use App\Services\Projects\ProjectListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request, ProjectListingService $listing): View
    {
        abort_unless($request->user()?->can('viewAny', Project::class), 403);

        return view('admin.projects.index', array_merge($this->lookupData(), [
            'projects' => $listing->paginate($request),
            'archived' => false,
        ]));
    }

    public function archived(Request $request, ProjectListingService $listing): View
    {
        abort_unless($request->user()?->can('viewAny', Project::class), 403);

        return view('admin.projects.index', array_merge($this->lookupData(), [
            'projects' => $listing->paginate($request, archived: true),
            'archived' => true,
        ]));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', Project::class), 403);

        return view('admin.projects.form', array_merge($this->lookupData(), [
            'project' => new Project,
            'parentProjects' => Project::query()->orderBy('name')->get(),
        ]));
    }

    public function store(ProjectRequest $request, ProjectLifecycleService $service): RedirectResponse
    {
        $project = $service->create($request->validated(), $request->user(), $request);

        return redirect()->route('admin.projects.show', $project)->with('status', 'project-created');
    }

    public function show(Request $request, Project $project): View
    {
        abort_unless($request->user()?->can('view', $project), 403);

        return view('admin.projects.show', [
            'project' => $project->load(['agency', 'category', 'status', 'priority', 'fundingSource', 'fiscalYear', 'country', 'division', 'district', 'upazila', 'union', 'ward', 'creator', 'activities.actor']),
        ]);
    }

    public function edit(Request $request, Project $project): View
    {
        abort_unless($request->user()?->can('update', $project), 403);

        return view('admin.projects.form', array_merge($this->lookupData(), [
            'project' => $project,
            'parentProjects' => Project::query()->whereKeyNot($project->id)->orderBy('name')->get(),
        ]));
    }

    public function update(ProjectRequest $request, Project $project, ProjectLifecycleService $service): RedirectResponse
    {
        $service->update($project, $request->validated(), $request->user(), $request);

        return redirect()->route('admin.projects.show', $project)->with('status', 'project-updated');
    }

    public function archive(Request $request, Project $project, ProjectLifecycleService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('archive', $project), 403);

        $service->archive($project, $request->user(), $request);

        return back()->with('status', 'project-archived');
    }

    public function restore(Request $request, Project $project, ProjectLifecycleService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('restore', $project), 403);

        $service->restore($project, $request->user(), $request);

        return back()->with('status', 'project-restored');
    }

    public function destroy(Request $request, Project $project, ProjectLifecycleService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('delete', $project), 403);

        $service->delete($project, $request->user(), $request);

        return redirect()->route('admin.projects.index')->with('status', 'project-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupData(): array
    {
        return [
            'agencies' => Agency::query()->orderBy('name')->get(),
            'categories' => ProjectCategory::query()->orderBy('name')->get(),
            'statuses' => ProjectStatus::query()->orderBy('sort_order')->orderBy('name')->get(),
            'priorities' => ProjectPriority::query()->orderBy('sort_order')->orderBy('name')->get(),
            'fundingSources' => FundingSource::query()->orderBy('name')->get(),
            'fiscalYears' => FiscalYear::query()->orderByDesc('starts_on')->get(),
            'countries' => Country::query()->orderBy('name')->get(),
        ];
    }
}
