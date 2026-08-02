<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\ArtifactFetcher;
use App\Contracts\Ingestion\MalwareScanner;
use App\Data\Ingestion\AcquisitionResult;
use App\Exceptions\Ingestion\AcquisitionFailed;
use App\Models\DiscoveredResource;
use App\Models\SourceEndpoint;

class SecureArtifactFetcher implements ArtifactFetcher
{
    public function __construct(
        private readonly SafeHttpTransport $transport,
        private readonly MalwareScanner $malwareScanner,
        private readonly ArtifactMediaTypeInspector $mediaTypeInspector,
    ) {}

    public function fetch(DiscoveredResource $resource): AcquisitionResult
    {
        $resource->loadMissing('endpoint');
        $endpoint = $resource->endpoint;

        if (! $endpoint instanceof SourceEndpoint) {
            throw new AcquisitionFailed('Discovered resource has no source endpoint.');
        }

        $response = $this->transport->get($resource->canonical_url, $endpoint);

        if ($response->content === '') {
            throw new AcquisitionFailed('Source returned an empty artifact.');
        }

        $mediaType = $this->mediaTypeInspector->inspect($response->mediaType, $response->content);

        if (! in_array($mediaType, config('civiclens.ingestion.allowed_media_types', []), true)) {
            throw new AcquisitionFailed('Source returned a media type that is not approved for ingestion.');
        }

        $scan = $this->malwareScanner->scan($response->content);

        return new AcquisitionResult(
            retrievalUrl: $response->url,
            content: $response->content,
            mediaType: $mediaType,
            byteSize: strlen($response->content),
            sha256: hash('sha256', $response->content),
            headers: $response->headers,
            malwareScan: $scan,
        );
    }
}
