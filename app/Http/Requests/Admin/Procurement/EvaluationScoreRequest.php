<?php

namespace App\Http\Requests\Admin\Procurement;

use App\Models\BidSubmission;
use Illuminate\Foundation\Http\FormRequest;

class EvaluationScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bidSubmission = $this->route('bidSubmission');

        return $bidSubmission instanceof BidSubmission
            ? ($this->user()?->can('update', $bidSubmission->tender) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'evaluation_criterion_id' => ['required', 'integer', 'exists:evaluation_criteria,id'],
            'committee_member_id' => ['nullable', 'integer', 'exists:committee_members,id'],
            'score' => ['required', 'numeric', 'min:0'],
            'comments' => ['nullable', 'string'],
        ];
    }
}
