<?php

namespace App\Http\Requests\Admin\Procurement;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;

class CloseContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contract = $this->route('contract');

        return $contract instanceof Contract
            ? ($this->user()?->can('update', $contract->tender) ?? false)
            : false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
        ];
    }
}
