<?php

namespace App\Http\Requests\Admin\Procurement;

use App\Models\Contract;
use App\Models\Tender;
use App\Models\VariationOrder;
use Illuminate\Foundation\Http\FormRequest;

class ApproveVariationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $variationOrder = $this->route('variationOrder');
        $contract = $variationOrder instanceof VariationOrder ? $variationOrder->contract : null;
        $tender = $contract instanceof Contract ? $contract->tender : null;

        return $tender instanceof Tender && ($this->user()?->can('update', $tender) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'schedule_extension_days' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
