<?php

namespace App\Http\Requests\Admin\Intelligence;

use App\Models\IntelligenceIndicator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $thresholds = json_decode((string) $this->input('thresholds'), true);

            if (! is_array($thresholds) || array_is_list($thresholds)) {
                $validator->errors()->add('thresholds', 'Thresholds must be a JSON object.');

                return;
            }

            foreach ($thresholds as $key => $value) {
                if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
                    $validator->errors()->add('thresholds', 'Each threshold must be a finite non-negative number.');

                    return;
                }

                if (in_array($key, ['max_utilization', 'max_progress'], true) && (float) $value > 100) {
                    $validator->errors()->add('thresholds', 'Percentage thresholds must be between 0 and 100.');

                    return;
                }
            }

            $hasWarning = array_key_exists('warning', $thresholds);
            $hasCritical = array_key_exists('critical', $thresholds);

            if ($hasWarning !== $hasCritical) {
                $validator->errors()->add('thresholds', 'Warning and critical thresholds must be configured together.');

                return;
            }

            if ($hasWarning && (float) $thresholds['warning'] > (float) $thresholds['critical']) {
                $validator->errors()->add('thresholds', 'The warning threshold cannot exceed the critical threshold.');
            }
        });
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
