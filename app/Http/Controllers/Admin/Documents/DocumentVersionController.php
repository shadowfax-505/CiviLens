<?php

namespace App\Http\Controllers\Admin\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Documents\DocumentVersionRequest;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\Documents\DocumentLifecycleService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentVersionController extends Controller
{
    public function download(Request $request, Document $document, DocumentVersion $version): StreamedResponse
    {
        abort_unless($request->user()?->can('view', $document) === true, 403);
        abort_unless($version->document_id === $document->id, 404);

        $disk = Storage::disk($version->storage_disk);

        abort_unless($disk->exists($version->storage_path), 404, 'Document version file is unavailable.');

        return response()->streamDownload(function () use ($disk, $version): void {
            $stream = $disk->readStream($version->storage_path);

            if (! is_resource($stream)) {
                abort(404, 'Document version file is unavailable.');
            }

            fpassthru($stream);

            fclose($stream);
        }, $version->original_filename);
    }

    public function store(DocumentVersionRequest $request, Document $document, DocumentLifecycleService $service): RedirectResponse
    {
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        $service->replaceFile($document, $file, AuthenticatedUser::from($request), $request->string('reason')->toString());

        return redirect()->route('admin.documents.show', $document)->with('status', 'document-version-created');
    }
}
