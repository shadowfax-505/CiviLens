<?php

namespace App\Http\Requests\Admin\Procurement;

use App\Models\Award;
use Illuminate\Foundation\Http\FormRequest;

class ContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        $award = $this->route('award');

        return $award instanceof Award
            ? ($this->user()?->can('update', $award->tender) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contract_number' => ['required', 'string', 'max:255', 'unique:contracts,contract_number'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
            'signed_at' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
