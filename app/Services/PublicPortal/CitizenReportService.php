<?php

namespace App\Services\PublicPortal;

use App\Events\CitizenReportArchived;
use App\Events\CitizenReportStatusChanged;
use App\Events\CitizenReportSubmitted;
use App\Jobs\NotifyCitizenReportSubmitted;
use App\Models\CitizenReport;
use App\Models\CitizenReportStatus;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CitizenReportService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(User $submitter, array $data): CitizenReport
    {
        $status = CitizenReportStatus::query()->where('is_default', true)->firstOrFail();

        $report = CitizenReport::query()->create(array_merge(
            Arr::only($data, [
                'citizen_report_category_id',
                'project_id',
                'agency_id',
                'document_id',
                'country_id',
                'division_id',
                'district_id',
                'upazila_id',
                'union_id',
                'ward_id',
                'title',
                'description',
                'location_text',
                'contact_preference',
                'attachment_disk',
                'attachment_path',
                'attachment_original_filename',
                'attachment_mime_type',
                'attachment_size',
            ]),
            [
                'public_uuid' => (string) Str::uuid(),
                'citizen_report_status_id' => $status->id,
                'submitter_id' => $submitter->id,
                'submitted_at' => now(),
            ],
        ));

        $this->activity($report, $submitter, 'submitted', 'Citizen report submitted.', [], [
            'citizen_report_status_id' => $status->id,
        ]);

        $this->activity($report, $submitter, 'acknowledgement_queued', 'Acknowledgement queued.');
        CitizenReportSubmitted::dispatch($report);

        return $report->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function changeStatus(CitizenReport $report, User $actor, array $data): CitizenReport
    {
        $old = [
            'citizen_report_status_id' => $report->citizen_report_status_id,
            'moderation_notes' => $report->moderation_notes,
            'assigned_to' => $report->assigned_to,
        ];

        $status = CitizenReportStatus::query()
            ->whereKey((int) $data['citizen_report_status_id'])
            ->firstOrFail();

        $report->forceFill([
            'citizen_report_status_id' => $status->id,
            'moderation_notes' => $data['moderation_notes'] ?? $report->moderation_notes,
            'assigned_to' => $data['assigned_to'] ?? $report->assigned_to,
            'resolved_at' => $status->is_terminal ? now() : $report->resolved_at,
        ])->save();

        $new = [
            'citizen_report_status_id' => $report->citizen_report_status_id,
            'moderation_notes' => $report->moderation_notes,
            'assigned_to' => $report->assigned_to,
        ];

        $this->activity($report, $actor, 'status_changed', $data['moderation_notes'] ?? null, $old, $new);
        CitizenReportStatusChanged::dispatch($report, $status);

        return $report->refresh();
    }

    public function archive(CitizenReport $report, User $actor): CitizenReport
    {
        $report->forceFill(['archived_at' => now()])->save();
        $this->activity($report, $actor, 'archived', 'Citizen report archived.');
        CitizenReportArchived::dispatch($report);

        return $report->refresh();
    }

    public function restore(CitizenReport $report, User $actor): CitizenReport
    {
        $report->forceFill(['archived_at' => null])->save();
        $this->activity($report, $actor, 'restored', 'Citizen report restored.');

        return $report->refresh();
    }

    public function resendAcknowledgement(CitizenReport $report, User $actor): void
    {
        $this->queueAcknowledgement($report, $actor, true);
    }

    private function queueAcknowledgement(CitizenReport $report, ?User $actor, bool $preventDuplicate): void
    {
        if ($preventDuplicate && $report->activities()
            ->where('event', 'acknowledgement_queued')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists()) {
            throw ValidationException::withMessages([
                'acknowledgement' => 'An acknowledgement was already queued in the last five minutes.',
            ]);
        }

        $this->activity($report, $actor, 'acknowledgement_queued', $preventDuplicate ? 'Acknowledgement resend queued.' : 'Acknowledgement queued.');
        NotifyCitizenReportSubmitted::dispatch($report);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function activity(CitizenReport $report, ?User $actor, string $event, ?string $notes = null, array $old = [], array $new = []): void
    {
        $report->activities()->create([
            'actor_id' => $actor?->id,
            'event' => $event,
            'notes' => $notes,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
