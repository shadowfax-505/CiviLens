<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Services\PublicPortal\PublicDashboardService;
use App\Services\PublicPortal\PublicNavigationService;
use Illuminate\View\View;

class PublicHomeController extends Controller
{
    public function __invoke(PublicDashboardService $dashboard, PublicNavigationService $navigation): View
    {
        return view('public.home', [
            'summary' => $dashboard->summary(),
            'navigation' => $navigation->links(),
        ]);
    }
}
