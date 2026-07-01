<?php

namespace App\Http\Controllers\Admin\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Analytics\AnalyticsFilterRequest;
use App\Models\AnalyticsAlert;
use App\Models\AnalyticsReport;
use Illuminate\View\View;

class AnalyticsAlertController extends Controller
{
    public function __invoke(AnalyticsFilterRequest $request): View
    {
        abort_unless($request->user()?->can('viewAny', AnalyticsReport::class) === true, 403);

        return view('admin.analytics.alerts', [
            'alerts' => AnalyticsAlert::query()->with('rule')->latest('triggered_at')->paginate(20)->withQueryString(),
        ]);
    }
}
