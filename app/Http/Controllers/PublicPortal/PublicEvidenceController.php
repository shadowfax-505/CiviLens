<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Models\PublicationDocument;
use App\Services\PublicPortal\PublicEvidenceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicEvidenceController extends Controller
{
    public function index(Request $request, PublicEvidenceService $evidence): View
    {
        $snapshot = $evidence->currentSnapshot();

        // Nothing published yet is a state to show, not an error. A site that
        // 404s before its first snapshot looks broken; one that says what it
        // is waiting for is telling the truth.
        if ($snapshot === null) {
            return view('public.evidence.unpublished');
        }

        return view('public.evidence.index', [
            'snapshot' => $snapshot,
            'documents' => $evidence->documents($snapshot, $request->only('q', 'publisher', 'kind')),
            'filters' => $request->only('q', 'publisher', 'kind'),
            'publishers' => $evidence->publishers($snapshot),
            'certification' => $evidence->certificationSummary($snapshot),
        ]);
    }

    public function show(PublicationDocument $document, PublicEvidenceService $evidence): View
    {
        $snapshot = $evidence->currentSnapshot();

        // A document belonging to a superseded snapshot is not served here.
        // Old snapshots stay in the database so a citation resolves, but only
        // one of them is what the site is currently saying.
        abort_if($snapshot === null || $document->publication_snapshot_id !== $snapshot->id, 404);

        return view('public.evidence.show', [
            'snapshot' => $snapshot,
            'document' => $document,
            'figures' => $evidence->figuresFor($document),
        ]);
    }

    public function notices(Request $request, PublicEvidenceService $evidence): View
    {
        $snapshot = $evidence->currentSnapshot();

        if ($snapshot === null) {
            return view('public.evidence.unpublished');
        }

        return view('public.evidence.notices', [
            'snapshot' => $snapshot,
            'notices' => $evidence->notices($snapshot, $request->only('q')),
            'filters' => $request->only('q'),
        ]);
    }
}
