<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Services\PublicPortal\PublicAgencyService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicAgencyController extends Controller
{
    public function index(Request $request, PublicAgencyService $agencies): View
    {
        return view('public.agencies.index', [
            'agencies' => $agencies->listing($request->only('q')),
            'filters' => $request->only('q'),
        ]);
    }

    public function show(Agency $agency, PublicAgencyService $agencies): View
    {
        return view('public.agencies.show', $agencies->detail($agency));
    }
}
