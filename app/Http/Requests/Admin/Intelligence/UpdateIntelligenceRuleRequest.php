<?php

namespace App\Http\Requests\Admin\Intelligence;

use App\Models\IntelligenceIndicator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntelligenceRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', IntelligenceIndicator::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'weight' => ['required', 'integer', 'min:0', 'max:100'],
            'severity_default' => ['required', Rule::in(['info', 'warning', 'critical'])],
            'thresholds' => ['required', 'json'],
            'description' => ['nullable', 'string', 'max:2000'],
            'documentation_url' => ['nullable', 'url', 'max:500'],
            'execution_frequency' => ['required', Rule::in(['manual', 'hourly', 'daily', 'weekly', 'monthly'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ruleData(): array
    {
        $validated = $this->validated();
        $decoded = json_decode((string) $validated['thresholds'], true);
        $validated['thresholds'] = is_array($decoded) ? $decoded : [];
        $validated['is_active'] = (bool) $validated['is_active'];
        $validated['priority'] = (int) $validated['priority'];
        $validated['weight'] = (int) $validated['weight'];

        return $validated;
    }
}
