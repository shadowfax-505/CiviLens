<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Models\ProcurementMethod;
use App\Models\TenderStatus;
use App\Services\PublicPortal\PublicProcurementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicProcurementController extends Controller
{
    public function __invoke(Request $request, PublicProcurementService $procurement): View
    {
        return view('public.procurement.index', [
            'tenders' => $procurement->listing($request->only('q', 'procurement_method_id', 'tender_status_id')),
            'filters' => $request->only('q', 'procurement_method_id', 'tender_status_id'),
            'methods' => ProcurementMethod::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => TenderStatus::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(PublicProcurementService $procurement, string $slug): View
    {
        return view('public.procurement.show', [
            'tender' => $procurement->findBySlug($slug),
        ]);
    }
}
