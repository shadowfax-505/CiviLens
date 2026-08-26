<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Models\CorrectionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The route by which a person can object to something published here.
 *
 * Publishing government records is not a reason to have no way of hearing an
 * objection. These pages are served as images a search engine can reach, which
 * is a different reach from a PDF on a ministry site, and that difference is
 * what someone can reasonably object to.
 */
class CorrectionRequestController extends Controller
{
    public function create(): View
    {
        return view('public.corrections.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'about' => ['required', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:2000'],
            'kind' => ['required', 'in:correction,withdrawal'],
            // Optional, deliberately. Requiring a name in order to be heard is
            // its own privacy problem, and an anonymous objection to a personal
            // detail is exactly the objection most worth hearing.
            'contact' => ['nullable', 'string', 'max:255'],
        ]);

        $correction = CorrectionRequest::query()->create([
            'reference' => (string) Str::uuid(),
            'about' => $data['about'],
            'reason' => $data['reason'],
            'kind' => $data['kind'],
            'contact' => $data['contact'] ?? null,
            'status' => CorrectionRequest::RECEIVED,
        ]);

        // The reference is the only way an anonymous requester can follow their
        // own request, so it is shown once and plainly.
        return back()->with('correction_reference', $correction->reference);
    }
}
