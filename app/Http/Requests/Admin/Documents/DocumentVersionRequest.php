<?php

namespace App\Http\Requests\Admin\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class DocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document !== null && ($this->user()?->can('update', $document) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowedExtensions = config('civiclens.documents.allowed_extensions');
        $allowedExtensions = is_array($allowedExtensions) ? $allowedExtensions : ['pdf'];

        return [
            'file' => ['required', File::types($allowedExtensions)->max((int) config('civiclens.documents.max_upload_kb'))],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
