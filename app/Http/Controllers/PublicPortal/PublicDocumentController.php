<?php

namespace App\Http\Controllers\PublicPortal;

use App\Events\PublicDocumentDownloaded;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Services\PublicPortal\PublicDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicDocumentController extends Controller
{
    public function index(Request $request, PublicDocumentService $documents): View
    {
        return view('public.documents.index', [
            'documents' => $documents->listing($request->only('q', 'document_type_id', 'document_category_id')),
            'filters' => $request->only('q', 'document_type_id', 'document_category_id'),
            'types' => DocumentType::query()->orderBy('name')->get(['id', 'name']),
            'categories' => DocumentCategory::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function download(Document $document, PublicDocumentService $documents): StreamedResponse
    {
        $document = $documents->findPublic($document);
        PublicDocumentDownloaded::dispatch($document);

        $disk = Storage::disk($document->storage_disk);

        abort_unless($disk->exists($document->storage_path), 404, 'Document file is unavailable.');

        return response()->streamDownload(function () use ($disk, $document): void {
            $stream = $disk->readStream($document->storage_path);

            if (! is_resource($stream)) {
                abort(404, 'Document file is unavailable.');
            }

            fpassthru($stream);

            fclose($stream);
        }, $document->original_filename);
    }
}
