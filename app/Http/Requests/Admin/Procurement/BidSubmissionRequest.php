<?php

namespace App\Http\Requests\Admin\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class BidSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('tender')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bidder_organization_id' => ['required', 'integer', 'exists:bidder_organizations,id'],
            'reference_number' => ['required', 'string', 'max:255', 'unique:bid_submissions,reference_number'],
            'submitted_at' => ['nullable', 'date'],
            'bid_amount' => ['nullable', 'numeric', 'min:0'],
            'bid_valid_until' => ['nullable', 'date', 'after_or_equal:submitted_at'],
            'bid_security_amount' => ['nullable', 'numeric', 'min:0'],
            'technical_proposal_summary' => ['nullable', 'string'],
            'financial_proposal_summary' => ['nullable', 'string'],
            'technical_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'financial_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
