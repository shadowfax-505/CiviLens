<?php

namespace App\Http\Controllers\Admin\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Documents\DocumentVersionRequest;
use App\Models\Document;
use App\Services\Documents\DocumentLifecycleService;
use App\Support\Http\AuthenticatedUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;

class DocumentVersionController extends Controller
{
    public function store(DocumentVersionRequest $request, Document $document, DocumentLifecycleService $service): RedirectResponse
    {
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        $service->replaceFile($document, $file, AuthenticatedUser::from($request), $request->string('reason')->toString());

        return redirect()->route('admin.documents.show', $document)->with('status', 'document-version-created');
    }
}
