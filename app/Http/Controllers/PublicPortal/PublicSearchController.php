<?php

namespace App\Http\Controllers\PublicPortal;

use App\Events\PublicSearchExecuted;
use App\Http\Controllers\Controller;
use App\Services\PublicPortal\PublicSearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicSearchController extends Controller
{
    public function __invoke(Request $request, PublicSearchService $search): View|RedirectResponse
    {
        if (
            $request->user()?->hasRole(config('civiclens.roles.admin')) === true
            || $request->user()?->hasRole(config('civiclens.roles.staff')) === true
        ) {
            return redirect()->route('admin.search.index', $request->only('q', 'module'));
        }

        $results = $search->search($request->only('q', 'module', 'page'));
        PublicSearchExecuted::dispatch((string) $request->query('q', ''), $results->total());

        return view('public.search.index', [
            'results' => $results,
            'filters' => $request->only('q', 'module'),
        ]);
    }
}
