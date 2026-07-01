<?php

namespace App\Http\Controllers\PublicPortal;

use App\Events\PublicDocumentDownloaded;
use App\Http\Controllers\Controller;
use App\Models\Document;
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
            'documents' => $documents->listing($request->only('q')),
            'filters' => $request->only('q'),
        ]);
    }

    public function download(Document $document, PublicDocumentService $documents): StreamedResponse
    {
        $document = $documents->findPublic($document);
        PublicDocumentDownloaded::dispatch($document);

        return Storage::disk($document->storage_disk)->download($document->storage_path, $document->original_filename);
    }
}
