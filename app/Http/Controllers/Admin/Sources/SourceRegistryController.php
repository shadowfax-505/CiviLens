<?php

namespace App\Http\Controllers\Admin\Sources;

use App\Exceptions\Ingestion\UnsafeSourceUrl;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sources\StoreSourceEndpointRequest;
use App\Http\Requests\Admin\Sources\StoreSourcePublisherRequest;
use App\Jobs\RunSourceEndpointCrawl;
use App\Models\SourceEndpoint;
use App\Models\SourcePublisher;
use App\Services\Ingestion\SourceRegistryService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SourceRegistryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('viewAny', SourcePublisher::class) === true, 403);

        return view('admin.sources.index', [
            'publishers' => SourcePublisher::query()->withCount('endpoints')->orderBy('name')->get(),
            'endpoints' => SourceEndpoint::query()
                ->with('publisher:id,name,source_class')
                ->withCount(['crawlRuns', 'discoveredResources'])
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function storePublisher(StoreSourcePublisherRequest $request, SourceRegistryService $registry): RedirectResponse
    {
        try {
            $registry->createPublisher($request->validated(), AuthenticatedUser::from($request));
        } catch (UnsafeSourceUrl $exception) {
            throw ValidationException::withMessages(['canonical_url' => $exception->getMessage()]);
        }

        return back()->with('status', 'source-publisher-created');
    }

    public function storeEndpoint(StoreSourceEndpointRequest $request, SourceRegistryService $registry): RedirectResponse
    {
        try {
            $registry->createEndpoint($request->validated(), AuthenticatedUser::from($request));
        } catch (UnsafeSourceUrl $exception) {
            throw ValidationException::withMessages(['base_url' => $exception->getMessage()]);
        }

        return back()->with('status', 'source-endpoint-created');
    }

    public function pause(Request $request, SourceEndpoint $endpoint, SourceRegistryService $registry): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $endpoint->publisher) === true, 403);
        $registry->pause($endpoint, AuthenticatedUser::from($request));

        return back()->with('status', 'source-endpoint-paused');
    }

    public function resume(Request $request, SourceEndpoint $endpoint, SourceRegistryService $registry): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $endpoint->publisher) === true, 403);
        $registry->resume($endpoint, AuthenticatedUser::from($request));

        return back()->with('status', 'source-endpoint-resumed');
    }

    public function run(Request $request, SourceEndpoint $endpoint, SourceRegistryService $registry): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $endpoint->publisher) === true, 403);
        abort_if($endpoint->isPaused(), 409, 'Resume the endpoint before starting a crawl.');
        $actor = AuthenticatedUser::from($request);
        $registry->queued($endpoint, $actor);
        RunSourceEndpointCrawl::dispatch($endpoint->id, $actor->id);

        return back()->with('status', 'source-crawl-queued');
    }
}
