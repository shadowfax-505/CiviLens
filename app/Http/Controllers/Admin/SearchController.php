<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use App\Models\SearchHistory;
use App\Models\SearchIndex;
use App\Services\Search\SearchAnalyticsService;
use App\Services\Search\SearchManager;
use App\Services\Search\SearchRegistry;
use App\Services\Search\SearchSuggestionService;
use App\Support\Http\AuthenticatedUser;
use App\Support\Search\SearchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, SearchManager $manager, SearchSuggestionService $suggestions, SearchRegistry $registry): View
    {
        $this->authorizeSearch($request);

        $query = SearchQuery::fromArray($request->query());
        $user = AuthenticatedUser::from($request);
        $results = $manager->search($query, $user);

        return view('admin.search.index', [
            'query' => $query,
            'results' => $results,
            'modules' => $registry->modules(),
            'suggestions' => $suggestions->suggest($query->query, $user),
            'savedSearches' => SavedSearch::query()->where('user_id', $user->id)->latest()->limit(8)->get(),
            'recentSearches' => SearchHistory::query()->where('user_id', $user->id)->latest()->limit(8)->get(),
        ]);
    }

    public function advanced(Request $request, SearchManager $manager, SearchSuggestionService $suggestions, SearchRegistry $registry): View
    {
        return $this->index($request, $manager, $suggestions, $registry)->with('advanced', true);
    }

    public function suggestions(Request $request, SearchSuggestionService $suggestions): JsonResponse
    {
        $this->authorizeSearch($request);

        return response()->json([
            'data' => $suggestions->suggest((string) $request->query('q', ''), AuthenticatedUser::from($request)),
        ]);
    }

    public function results(Request $request, SearchManager $manager): JsonResponse
    {
        $this->authorizeSearch($request);

        $query = SearchQuery::fromArray($request->query());
        $results = $manager->search($query, AuthenticatedUser::from($request));

        return response()->json([
            'data' => $results->items(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    public function save(Request $request, SearchAnalyticsService $analytics): RedirectResponse
    {
        $this->authorizeSearch($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:80'],
            'sort' => ['nullable', 'string', 'max:80'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $analytics->saveSearch(AuthenticatedUser::from($request), $validated['name'], SearchQuery::fromArray($validated));

        return back()->with('status', 'saved-search-created');
    }

    public function click(Request $request, SearchAnalyticsService $analytics): RedirectResponse
    {
        $this->authorizeSearch($request);

        $validated = $request->validate([
            'search_index_id' => ['required', 'integer', 'exists:search_indexes,id'],
            'q' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $index = SearchIndex::query()
            ->whereKey((int) $validated['search_index_id'])
            ->firstOrFail();

        $analytics->recordClick(AuthenticatedUser::from($request), $index, (string) ($validated['q'] ?? ''), (int) ($validated['position'] ?? 0));

        return redirect($index->url ?? route('admin.search.index'));
    }

    private function authorizeSearch(Request $request): void
    {
        $user = $request->user();

        abort_unless($user !== null && (
            $user->hasRole(config('civiclens.roles.admin'))
            || $user->hasPermission(config('civiclens.permissions.search_manage'))
        ), 403);
    }
}
