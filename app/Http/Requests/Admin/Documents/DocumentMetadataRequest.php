<?php

namespace App\Http\Requests\Admin\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentMetadataRequest extends FormRequest
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
        return [
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'document_category_id' => ['nullable', 'integer', 'exists:document_categories,id'],
            'document_status_id' => ['required', 'integer', 'exists:document_statuses,id'],
            'document_visibility_id' => ['required', 'integer', 'exists:document_visibilities,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'language' => ['nullable', 'string', 'max:16'],
            'page_count' => ['nullable', 'integer', 'min:1'],
            'documentable_type' => ['nullable', 'string', Rule::in(['project', 'budget', 'tender', 'contract', 'contractor', 'organization', 'agency', 'country', 'division', 'district', 'upazila', 'union', 'ward'])],
            'documentable_id' => ['nullable', 'integer'],
            'relationship_type' => ['nullable', 'string', 'max:80'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:document_tags,id'],
        ];
    }
}
