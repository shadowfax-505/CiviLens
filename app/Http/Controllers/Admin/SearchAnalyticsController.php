<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Search\SearchAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchAnalyticsController extends Controller
{
    public function __invoke(Request $request, SearchAnalyticsService $analytics): View
    {
        $user = $request->user();

        abort_unless($user !== null && (
            $user->hasRole(config('civiclens.roles.admin'))
            || $user->hasPermission(config('civiclens.permissions.analytics_view'))
            || $user->hasPermission(config('civiclens.permissions.search_manage'))
        ), 403);

        return view('admin.search.analytics', [
            'summary' => $analytics->summary(),
        ]);
    }
}
