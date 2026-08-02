<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicPortal\ResolveDistrictRequest;
use App\Services\Geography\DistrictLocatorService;
use Illuminate\Http\JsonResponse;

class PublicDistrictLocatorController extends Controller
{
    public function __invoke(ResolveDistrictRequest $request, DistrictLocatorService $locator): JsonResponse
    {
        $district = $locator->locate(
            (float) $request->validated('latitude'),
            (float) $request->validated('longitude'),
        );

        return response()->json([
            'district' => $district === null ? null : ['id' => $district->id, 'name' => $district->name],
            'url' => route('public.projects.index', array_filter(['district_id' => $district?->id])),
        ])->header('Cache-Control', 'no-store, private');
    }
}
