<?php

namespace App\Http\Controllers\Admin\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Documents\DocumentMetadataRequest;
use App\Http\Requests\Admin\Documents\DocumentUploadRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentStatus;
use App\Models\DocumentTag;
use App\Models\DocumentType;
use App\Models\DocumentVisibility;
use App\Models\Project;
use App\Models\User;
use App\Services\Documents\DocumentDashboardService;
use App\Services\Documents\DocumentLifecycleService;
use App\Services\Documents\DocumentListingService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request, DocumentListingService $listing, DocumentDashboardService $dashboard): View
    {
        abort_unless($request->user()?->can('viewAny', Document::class) === true, 403);

        return view('admin.documents.index', array_merge($this->lookupData(), [
            'documents' => $listing->paginate($request),
            'summary' => $dashboard->summary(),
            'archived' => false,
        ]));
    }

    public function archived(Request $request, DocumentListingService $listing, DocumentDashboardService $dashboard): View
    {
        abort_unless($request->user()?->can('viewAny', Document::class) === true, 403);

        return view('admin.documents.index', array_merge($this->lookupData(), [
            'documents' => $listing->paginate($request, archived: true),
            'summary' => $dashboard->summary(),
            'archived' => true,
        ]));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('create', Document::class) === true, 403);

        return view('admin.documents.form', array_merge($this->lookupData(), [
            'document' => new Document,
        ]));
    }

    public function store(DocumentUploadRequest $request, DocumentLifecycleService $service): RedirectResponse
    {
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        $document = $service->upload($request->validated(), $file, AuthenticatedUser::from($request));

        return redirect()->route('admin.documents.show', $document)->with('status', 'document-uploaded');
    }

    public function show(Request $request, Document $document): View
    {
        abort_unless($request->user()?->can('view', $document) === true, 403);

        return view('admin.documents.show', [
            'document' => $document->load(['type', 'category', 'status', 'visibility', 'owner', 'uploader', 'tags', 'versions', 'documentables', 'activities']),
        ]);
    }

    public function edit(Request $request, Document $document): View
    {
        abort_unless($request->user()?->can('update', $document) === true, 403);

        return view('admin.documents.form', array_merge($this->lookupData(), [
            'document' => $document->load('tags'),
        ]));
    }

    public function update(DocumentMetadataRequest $request, Document $document, DocumentLifecycleService $service): RedirectResponse
    {
        $service->updateMetadata($document, $request->validated(), AuthenticatedUser::from($request));

        return redirect()->route('admin.documents.show', $document)->with('status', 'document-updated');
    }

    public function archive(Request $request, Document $document, DocumentLifecycleService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('archive', $document) === true, 403);

        $service->archive($document, AuthenticatedUser::from($request));

        return back()->with('status', 'document-archived');
    }

    public function restore(Request $request, Document $document, DocumentLifecycleService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('restore', $document) === true, 403);

        $service->restore($document, AuthenticatedUser::from($request));

        return back()->with('status', 'document-restored');
    }

    public function download(Request $request, Document $document, DocumentLifecycleService $service): StreamedResponse
    {
        abort_unless($request->user()?->can('download', $document) === true, 403);

        $service->recordDownload($document, AuthenticatedUser::from($request));

        return Storage::disk($document->storage_disk)->download($document->storage_path, $document->original_filename);
    }

    public function preview(Request $request, Document $document): View
    {
        abort_unless($request->user()?->can('view', $document) === true, 403);

        return view('admin.documents.preview', ['document' => $document]);
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupData(): array
    {
        return [
            'types' => DocumentType::query()->orderBy('name')->get(),
            'categories' => DocumentCategory::query()->orderBy('name')->get(),
            'statuses' => DocumentStatus::query()->orderBy('sort_order')->orderBy('name')->get(),
            'visibilities' => DocumentVisibility::query()->orderBy('sort_order')->orderBy('name')->get(),
            'tags' => DocumentTag::query()->orderBy('name')->get(),
            'projects' => Project::query()->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
        ];
    }
}
