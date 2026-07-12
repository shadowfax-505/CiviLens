<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\MapViewportRequest;
use App\Services\Projects\ProjectMapQueryService;
use Illuminate\Http\JsonResponse;

class PublicProjectMapDataController extends Controller
{
    public function __invoke(MapViewportRequest $request, ProjectMapQueryService $projects): JsonResponse
    {
        $markers = $projects->publicMarkers($request->viewport());
        $limit = (int) config('civiclens.maps.public_marker_limit');

        return response()->json([
            'data' => $markers->take($limit)->values(),
            'meta' => ['count' => min($markers->count(), $limit), 'capped' => $markers->count() > $limit],
        ]);
    }
}
