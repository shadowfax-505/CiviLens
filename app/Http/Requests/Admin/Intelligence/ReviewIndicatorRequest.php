<?php

namespace App\Http\Requests\Admin\Intelligence;

use App\Models\IntelligenceIndicator;
use Illuminate\Foundation\Http\FormRequest;

class ReviewIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->indicator()) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:in_review,accepted,dismissed,needs_more_evidence'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'follow_up_on' => ['nullable', 'date'],
        ];
    }

    public function indicator(): IntelligenceIndicator
    {
        $indicator = $this->route('indicator');

        if ($indicator instanceof IntelligenceIndicator) {
            return $indicator;
        }

        return IntelligenceIndicator::query()->whereKey($indicator)->firstOrFail();
    }
}
