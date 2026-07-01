<?php

namespace App\Http\Controllers\PublicPortal;

use App\Events\PublicProjectViewed;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\PublicPortal\PublicProjectService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicProjectController extends Controller
{
    public function index(Request $request, PublicProjectService $projects): View
    {
        return view('public.projects.index', [
            'projects' => $projects->listing($request->only('q')),
            'filters' => $request->only('q'),
        ]);
    }

    public function show(Project $project, PublicProjectService $projects): View
    {
        PublicProjectViewed::dispatch($project);

        return view('public.projects.show', $projects->detail($project));
    }
}
