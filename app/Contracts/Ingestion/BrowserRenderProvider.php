<?php

namespace App\Contracts\Ingestion;

use App\Models\SourceEndpoint;

interface BrowserRenderProvider
{
    public function render(SourceEndpoint $endpoint): string;
}
