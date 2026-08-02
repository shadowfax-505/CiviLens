<?php

namespace App\Http\Requests\Admin\Sources;

use App\Models\SourcePublisher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSourceEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SourcePublisher::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_publisher_id' => ['required', 'integer', 'exists:source_publishers,id'],
            'name' => ['required', 'string', 'max:255'],
            'connector_type' => ['required', Rule::in(['api', 'feed', 'sitemap', 'direct_download', 'static_html', 'browser'])],
            'base_url' => ['required', 'url:https', 'max:2048'],
            'allowed_hosts' => ['required', 'array', 'min:1', 'max:10'],
            'allowed_hosts.*' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?!-)[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*(?<!-)$/'],
            'allowed_path_prefixes' => ['required', 'array', 'min:1', 'max:20'],
            'allowed_path_prefixes.*' => ['required', 'string', 'starts_with:/', 'max:500', 'not_regex:/[\x00-\x1F\x7F]/'],
            'access_decision' => ['required', 'string', 'max:64'],
            'access_reviewed_at' => ['required', 'date', 'before_or_equal:today'],
            'crawl_interval_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
            'rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:120'],
            'timeout_seconds' => ['required', 'integer', 'min:2', 'max:60'],
            'max_content_bytes' => ['required', 'integer', 'min:1024', 'max:'.config('civiclens.ingestion.absolute_max_content_bytes')],
        ];
    }
}
