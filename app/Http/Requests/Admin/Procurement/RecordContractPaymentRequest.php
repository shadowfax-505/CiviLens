<?php

namespace App\Http\Requests\Admin\Procurement;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;

class RecordContractPaymentRequest extends FormRequest
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
            'payment_reference' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
            'status' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
