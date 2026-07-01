<?php

namespace App\Http\Requests\Admin\Procurement;

use App\Models\BidSubmission;
use Illuminate\Foundation\Http\FormRequest;

class OpenBidRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bidSubmission = $this->route('bidSubmission');

        return $bidSubmission instanceof BidSubmission
            ? ($this->user()?->can('update', $bidSubmission->tender) ?? false)
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
