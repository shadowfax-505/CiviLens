<?php

namespace App\Support\Observability;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StructuredLogContext
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRequest(Request $request): array
    {
        return [
            'request_id' => $request->headers->get('X-Request-Id', (string) Str::uuid()),
            'method' => $request->method(),
            'path' => $request->path(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ];
    }
}
