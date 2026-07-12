<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MapViewportRequest;
use App\Models\Project;
use App\Services\Projects\ProjectMapQueryService;
use Illuminate\Http\JsonResponse;

class ProjectMapDataController extends Controller
{
    public function __invoke(MapViewportRequest $request, ProjectMapQueryService $projects): JsonResponse
    {
        abort_unless($request->user()?->can('viewAny', Project::class) === true, 403);

        $markers = $projects->adminMarkers($request->viewport());
        $limit = (int) config('civiclens.maps.admin_marker_limit');

        return response()->json([
            'data' => $markers->take($limit)->values(),
            'meta' => ['count' => min($markers->count(), $limit), 'capped' => $markers->count() > $limit],
        ]);
    }
}
