<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Models\ExtractionPage;
use App\Models\PublicationFigure;
use App\Models\PublicationSnapshot;
use App\Models\SourceArtifactVersion;
use App\Services\Extraction\ArtifactWorkspace;
use App\Services\Extraction\EvidenceImagePainter;
use App\Services\Extraction\PageRasterizer;
use App\Services\Extraction\ValueLocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The scanned page a published figure was read from, with the figure marked.
 *
 * This is what makes the published number checkable by someone who does not
 * trust it. A page number in a table is a reference; the page itself, with the
 * characters ringed, is evidence — and every claim this project makes rests on
 * a reader being able to go and look.
 *
 * Keyed on the published figure rather than the live field. A snapshot is what
 * the site stands behind, so what can be traced is exactly what was published:
 * a value withheld, deferred or rejected has no page here, because there is no
 * claim about it to check.
 */
class PublicEvidenceImageController extends Controller
{
    public function __invoke(
        Request $request,
        PublicationFigure $figure,
        ArtifactWorkspace $workspace,
        PageRasterizer $rasterizer,
        ValueLocator $locator,
        EvidenceImagePainter $painter,
    ): Response {
        $current = PublicationSnapshot::current();

        // Only the snapshot the site is currently serving. An older snapshot's
        // figures stay resolvable as data, but the page behind a superseded
        // claim is not what this site is asserting today.
        abort_if($current === null || $figure->publication_snapshot_id !== $current->id, 404);

        $field = $figure->field;

        abort_unless($field !== null, 404);

        $page = $field->page;

        $artifact = $field->run?->artifactVersion;

        abort_unless($artifact instanceof SourceArtifactVersion, 404);

        // Rendered once per page and kept. Rasterising a PDF page on demand is
        // the most expensive thing this application does, and a public URL that
        // does it per request is a denial of service with extra steps.
        $cacheKey = 'public-pages/'.$field->extraction_page_id.'.png';
        $disk = Storage::disk('local');

        if ($disk->exists($cacheKey)) {
            return $this->respond((string) $disk->get($cacheKey), $figure, $locator, $painter, $request->boolean('crop'));
        }

        $materialized = null;
        $image = null;

        try {
            $materialized = $workspace->materialize($artifact);
            $image = $rasterizer->rasterize(
                $materialized,
                max(1, (int) $figure->page_number),
                // The DPI the boxes were measured at, taken from the page rather
                // than from configuration. A page read by the enhanced pass is
                // recognised at 300 while this rendered at 150, so its boxes landed
                // off the raster and the crop came back a single pixel wide — the
                // reviewer being shown a blank sliver of a page they were asked to
                // judge. 24 pages are read that way and four had already been
                // adjudicated from that screen.
                $page->recognized_dpi ?? (int) config('civiclens.extraction.review_page_dpi', 150),
            );

            $png = file_get_contents($image);

            abort_if($png === false, 404);

            $disk->put($cacheKey, $png);

            return $this->respond($png, $figure, $locator, $painter, $request->boolean('crop'));
        } catch (Throwable) {
            abort(404);
        } finally {
            if ($image !== null) {
                $rasterizer->discard($image);
            }

            $workspace->discard($materialized);
        }
    }

    private function respond(
        string $png,
        PublicationFigure $figure,
        ValueLocator $locator,
        EvidenceImagePainter $painter,
        bool $wantsCrop,
    ): Response {
        $field = $figure->field;
        $page = $field?->page;

        $boxes = $page instanceof ExtractionPage && $field !== null
            ? $locator->locate($field, $page)
            : [];

        $headers = [
            'Content-Type' => 'image/png',
            // Immutable: the page behind a published figure does not change,
            // because the snapshot it belongs to does not.
            'Cache-Control' => 'public, max-age=86400',
        ];

        try {
            if ($boxes === []) {
                // Read before word geometry was stored. The whole page is still
                // the honest thing to show, and the page around it says the
                // figure could not be pinpointed.
                return response($png, 200, $headers);
            }

            return response(
                $wantsCrop ? $painter->crop($png, $boxes[0]) : $painter->highlight($png, $boxes),
                200,
                $headers,
            );
        } catch (Throwable) {
            return response($png, 200, $headers);
        }
    }
}
