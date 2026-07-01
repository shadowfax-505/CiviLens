<?php

namespace App\Http\Requests\Admin\Procurement;

use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\Tender;
use Illuminate\Foundation\Http\FormRequest;

class CompleteMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        $milestone = $this->route('milestone');
        $contract = $milestone instanceof ContractMilestone ? $milestone->contract : null;
        $tender = $contract instanceof Contract ? $contract->tender : null;

        return $tender instanceof Tender && ($this->user()?->can('update', $tender) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'completion_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'evidence_summary' => ['nullable', 'string'],
        ];
    }
}
