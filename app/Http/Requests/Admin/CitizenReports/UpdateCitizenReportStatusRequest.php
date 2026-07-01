<?php

namespace App\Http\Requests\Admin\CitizenReports;

use App\Models\CitizenReport;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCitizenReportStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->report()) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'citizen_report_status_id' => ['required', 'exists:citizen_report_statuses,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'moderation_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function report(): CitizenReport
    {
        $report = $this->route('report');

        if ($report instanceof CitizenReport) {
            return $report;
        }

        return CitizenReport::query()->whereKey($report)->firstOrFail();
    }
}
