<?php

namespace App\Contracts\Ingestion;

use App\Data\Ingestion\AcquisitionResult;
use App\Models\DiscoveredResource;

interface ArtifactFetcher
{
    public function fetch(DiscoveredResource $resource): AcquisitionResult;
}
