<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsReport;
use App\Services\Executive\ExecutiveDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExecutiveDashboardController extends Controller
{
    public function __invoke(Request $request, ExecutiveDashboardService $dashboard): View
    {
        return view('dashboard', [
            'summary' => $request->user()?->can('viewAny', AnalyticsReport::class) === true ? $dashboard->summary() : null,
        ]);
    }
}
