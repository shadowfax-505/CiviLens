<?php

namespace App\Http\Requests\Admin\Intelligence;

use App\Models\IntelligenceIndicator;
use Illuminate\Foundation\Http\FormRequest;

class IndicatorFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', IntelligenceIndicator::class) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'severity' => ['nullable', 'in:info,warning,critical'],
            'status' => ['nullable', 'in:pending,in_review,accepted,dismissed,needs_more_evidence'],
            'module' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->validated();
    }
}
