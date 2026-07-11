<?php

namespace App\Http\Controllers\Admin\Geography;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Geography\UpdateProjectLocationRequest;
use App\Models\AdministrativeUnion;
use App\Models\Country;
use App\Models\District;
use App\Models\Division;
use App\Models\Project;
use App\Models\Upazila;
use App\Models\Ward;
use App\Services\Projects\ProjectLifecycleService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectMapController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('viewAny', Project::class) === true, 403);

        $projects = Project::query()
            ->with(['agency', 'country', 'division', 'district', 'upazila', 'union', 'ward'])
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $selectedProjectId = $request->integer('project_id');
        $selectedProject = $selectedProjectId
            ? Project::query()->with(['agency', 'country', 'division', 'district', 'upazila', 'union', 'ward'])->findOrFail($selectedProjectId)
            : $projects->first();

        return view('admin.geography.project-map', [
            'projects' => $projects,
            'selectedProject' => $selectedProject,
            'countries' => Country::query()->orderBy('name')->get(),
            'divisions' => Division::query()->orderBy('name')->get(),
            'districts' => District::query()->orderBy('name')->get(),
            'upazilas' => Upazila::query()->orderBy('name')->get(),
            'unions' => AdministrativeUnion::query()->orderBy('name')->get(),
            'wards' => Ward::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateProjectLocationRequest $request, Project $project, ProjectLifecycleService $service): RedirectResponse
    {
        $service->update($project, $request->validated(), AuthenticatedUser::from($request), $request);

        return redirect()
            ->route('admin.projects.map', ['project_id' => $project->id])
            ->with('status', 'project-location-updated');
    }
}
