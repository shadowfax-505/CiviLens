<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Services\PublicPortal\PublicProcurementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicProcurementController extends Controller
{
    public function __invoke(Request $request, PublicProcurementService $procurement): View
    {
        return view('public.procurement.index', [
            'tenders' => $procurement->listing($request->only('q')),
            'filters' => $request->only('q'),
        ]);
    }
}
