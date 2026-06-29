<?php

namespace App\Http\Controllers\Admin\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Documents\DocumentBulkActionRequest;
use App\Services\Documents\DocumentLifecycleService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;

class DocumentBulkActionController extends Controller
{
    public function store(DocumentBulkActionRequest $request, DocumentLifecycleService $service): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['action'] === 'archive') {
            $service->bulkArchive($validated['document_ids'], AuthenticatedUser::from($request));
        }

        if ($validated['action'] === 'tag') {
            $service->bulkTag($validated['document_ids'], $validated['tag_ids'] ?? [], AuthenticatedUser::from($request));
        }

        return back()->with('status', 'document-bulk-action-complete');
    }
}
