<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChangeRequestController extends Controller
{
    private const MODULES = [
        'projects' => 'Projects',
        'documents' => 'Documents',
        'contractors' => 'Contractors',
        'procurement' => 'Procurement',
        'budgets' => 'Budgets',
        'agencies' => 'Agencies',
    ];

    private const OPERATIONS = ['create', 'update', 'archive', 'restore', 'publish', 'close'];

    public function index(Request $request): View
    {
        $this->authorizeQueueAccess($request);

        $user = $request->user();
        $query = ChangeRequest::query()->with(['requester', 'reviewer'])->latest();

        if ($user?->hasRole(config('civiclens.roles.admin')) !== true) {
            $query->where('requester_id', $user?->id);
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        if ($request->filled('module')) {
            $query->where('module', (string) $request->string('module'));
        }

        return view('admin.change-requests.index', [
            'changeRequests' => $query->paginate(12)->withQueryString(),
            'modules' => self::MODULES,
            'filters' => $request->only('status', 'module'),
            'canReview' => $user?->hasRole(config('civiclens.roles.admin')) === true,
            'canSubmit' => $user?->hasRole(config('civiclens.roles.staff')) === true,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeSubmitter($request);

        return view('admin.change-requests.create', [
            'modules' => self::MODULES,
            'defaults' => $request->only('module', 'operation', 'subject_type', 'subject_id', 'target_id', 'subject_label', 'subject_url', 'target_field', 'current_value', 'proposed_value', 'summary', 'details', 'payload'),
            'operations' => self::OPERATIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSubmitter($request);

        $validated = $request->validate([
            'module' => ['required', 'in:'.implode(',', array_keys(self::MODULES))],
            'operation' => ['nullable', 'in:'.implode(',', self::OPERATIONS)],
            'subject_type' => ['nullable', 'string', 'max:120'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'target_id' => ['nullable', 'integer', 'min:1'],
            'subject_label' => ['required', 'string', 'max:255'],
            'subject_url' => ['nullable', 'url', 'max:2048'],
            'target_field' => ['nullable', 'string', 'max:120'],
            'current_value' => ['nullable', 'string', 'max:5000'],
            'proposed_value' => ['nullable', 'string', 'max:5000'],
            'summary' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:10000'],
            'metadata' => ['nullable', 'array'],
            'payload' => ['nullable', 'json'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $attachment = $request->file('attachment');
        $changeRequest = ChangeRequest::query()->create([
            ...Arr::except($validated, ['attachment', 'payload']),
            'operation' => $validated['operation'] ?? 'update',
            'target_id' => $validated['target_id'] ?? $validated['subject_id'] ?? null,
            'payload' => isset($validated['payload']) ? json_decode($validated['payload'], true, flags: JSON_THROW_ON_ERROR) : null,
            'attachment_path' => $attachment?->store('change-requests'),
            'attachment_name' => $attachment?->getClientOriginalName(),
            'attachment_mime_type' => $attachment?->getMimeType(),
            'requester_id' => (int) $request->user()?->id,
            'status' => 'pending',
        ]);
        $this->activity($changeRequest, $request, 'submitted', 'Proposal submitted for review.');

        return redirect()->route('admin.change-requests.show', $changeRequest)->with('status', 'change-request-submitted');
    }

    public function show(Request $request, ChangeRequest $changeRequest): View
    {
        $this->authorizeQueueAccess($request);
        $this->authorizeView($request, $changeRequest);

        return view('admin.change-requests.show', [
            'changeRequest' => $changeRequest->load(['requester', 'reviewer', 'activities.actor']),
            'canReview' => $request->user()?->hasRole(config('civiclens.roles.admin')) === true,
            'modules' => self::MODULES,
        ]);
    }

    public function update(Request $request, ChangeRequest $changeRequest): RedirectResponse
    {
        $this->authorizeQueueAccess($request);
        abort_unless($request->user()?->hasRole(config('civiclens.roles.admin')) === true, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected,dismissed'],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $changeRequest->update([
            'status' => $validated['status'],
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewer_id' => (int) $request->user()->id,
            'reviewed_at' => now(),
        ]);
        $this->activity($changeRequest, $request, 'reviewed', $validated['review_notes'] ?? null, ['status' => $validated['status']]);

        return back()->with('status', 'change-request-updated');
    }

    public function markApplied(Request $request, ChangeRequest $changeRequest): RedirectResponse
    {
        $this->authorizeQueueAccess($request);
        abort_unless($request->user()?->hasRole(config('civiclens.roles.admin')) === true, 403);
        abort_unless($changeRequest->status === 'approved', 422, 'Only approved proposals can be marked as applied.');

        $validated = $request->validate(['application_notes' => ['nullable', 'string', 'max:5000']]);
        $this->activity($changeRequest, $request, 'applied_to_source', $validated['application_notes'] ?? null);

        return back()->with('status', 'change-request-applied');
    }

    public function downloadAttachment(Request $request, ChangeRequest $changeRequest): StreamedResponse
    {
        $this->authorizeQueueAccess($request);
        $this->authorizeView($request, $changeRequest);

        abort_unless($changeRequest->attachment_path !== null, 404);

        return Storage::download($changeRequest->attachment_path, $changeRequest->attachment_name);
    }

    private function authorizeQueueAccess(Request $request): void
    {
        abort_unless($request->user() !== null, 403);
        abort_unless(
            $request->user()->hasRole(config('civiclens.roles.admin')) === true
            || $request->user()->hasRole(config('civiclens.roles.staff')) === true,
            403,
        );
    }

    private function authorizeSubmitter(Request $request): void
    {
        abort_unless($request->user()?->hasRole(config('civiclens.roles.staff')) === true, 403);
    }

    private function authorizeView(Request $request, ChangeRequest $changeRequest): void
    {
        $user = $request->user();

        abort_unless(
            $user?->hasRole(config('civiclens.roles.admin')) === true
            || $changeRequest->requester_id === $user?->id,
            403,
        );
    }

    /** @param array<string, mixed> $metadata */
    private function activity(ChangeRequest $changeRequest, Request $request, string $event, ?string $notes = null, array $metadata = []): void
    {
        $changeRequest->activities()->create([
            'actor_id' => $request->user()?->id,
            'event' => $event,
            'notes' => $notes,
            'metadata' => $metadata,
        ]);
    }
}
