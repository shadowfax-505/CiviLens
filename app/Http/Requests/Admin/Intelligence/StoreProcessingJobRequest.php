<?php

namespace App\Http\Requests\Admin\Intelligence;

use App\Models\Document;
use App\Models\IntelligenceIndicator;
use App\Models\IntelligenceProcessingJob;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcessingJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', IntelligenceProcessingJob::class) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::in(array_keys($this->targetMap()))],
            'target_id' => ['required', 'integer', function (string $attribute, mixed $value, \Closure $fail): void {
                $class = $this->targetMap()[$this->input('target_type')] ?? null;

                if (! is_string($class) || ! $class::query()->whereKey($value)->exists()) {
                    $fail('The selected target record is invalid.');
                }
            }],
            'job_type' => ['required', Rule::in(['ocr_preparation', 'ai_review_preparation', 'search_sync'])],
        ];
    }

    public function target(): Model
    {
        $class = $this->targetMap()[$this->validated('target_type')];

        return $class::query()->whereKey($this->validated('target_id'))->firstOrFail();
    }

    public function jobType(): string
    {
        return $this->validated('job_type');
    }

    /**
     * @return array<string, class-string<Model>>
     */
    private function targetMap(): array
    {
        return [
            'projects' => Project::class,
            'documents' => Document::class,
            'indicators' => IntelligenceIndicator::class,
        ];
    }
}
