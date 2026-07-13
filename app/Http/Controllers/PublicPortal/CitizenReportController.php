<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreCitizenReportRequest;
use App\Models\CitizenReport;
use App\Models\CitizenReportCategory;
use App\Models\User;
use App\Services\PublicPortal\CitizenReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CitizenReportController extends Controller
{
    public function create(): View
    {
        return view('public.reports.create', [
            'categories' => CitizenReportCategory::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(StoreCitizenReportRequest $request, CitizenReportService $reports): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $data = $request->validated();
        $attachment = $request->file('attachment');

        if ($attachment instanceof UploadedFile) {
            $path = $attachment->store('citizen-reports', 'local');

            $data['attachment_disk'] = 'local';
            $data['attachment_path'] = $path;
            $data['attachment_original_filename'] = $attachment->getClientOriginalName();
            $data['attachment_mime_type'] = $attachment->getMimeType();
            $data['attachment_size'] = $attachment->getSize();
        }

        $report = $reports->submit($user, $data);

        return redirect()
            ->route('citizen.reports.index', ['submitted' => $report->id])
            ->with('status', 'Your report was submitted for review.');
    }

    public function show(Request $request, string $uuid): View
    {
        $report = CitizenReport::query()->with(['category', 'status', 'activities'])->where('public_uuid', $uuid)->firstOrFail();
        abort_unless($request->user()?->can('view', $report) === true, 403);

        return view('public.reports.show', [
            'report' => $report,
        ]);
    }

    public function downloadAttachment(Request $request, CitizenReport $report): StreamedResponse
    {
        abort_unless($request->user()?->can('view', $report) === true, 403);
        abort_unless($report->attachment_path !== null, 404, 'Attachment is unavailable.');

        $disk = Storage::disk($report->attachment_disk ?? 'local');
        abort_unless($disk->exists($report->attachment_path), 404, 'Attachment is unavailable.');

        return response()->streamDownload(function () use ($disk, $report): void {
            $stream = $disk->readStream($report->attachment_path);

            if (! is_resource($stream)) {
                abort(404, 'Attachment is unavailable.');
            }

            fpassthru($stream);

            fclose($stream);
        }, $report->attachment_original_filename ?? 'citizen-report-attachment', [
            'Content-Type' => $report->attachment_mime_type ?? 'application/octet-stream',
        ]);
    }
}
