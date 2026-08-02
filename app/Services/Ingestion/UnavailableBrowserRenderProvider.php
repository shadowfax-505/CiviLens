<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\BrowserRenderProvider;
use App\Exceptions\Ingestion\AcquisitionFailed;
use App\Models\SourceEndpoint;

class UnavailableBrowserRenderProvider implements BrowserRenderProvider
{
    public function render(SourceEndpoint $endpoint): string
    {
        throw new AcquisitionFailed('The isolated browser-render worker is not configured.');
    }
}
