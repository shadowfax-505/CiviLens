<?php

namespace App\Http\Controllers\Admin\Sources;

use App\Http\Controllers\Controller;
use App\Models\ExtractionField;
use App\Models\ExtractionPage;
use App\Models\SourceArtifactVersion;
use App\Models\SourcePublisher;
use App\Services\Extraction\ArtifactWorkspace;
use App\Services\Extraction\EvidenceImagePainter;
use App\Services\Extraction\PageRasterizer;
use App\Services\Extraction\ValueLocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Serve the scanned page an extracted value came from.
 *
 * Without it the review queue asks a question nobody can answer. A reviewer was
 * shown the extracted value beside the recognized text around it, which is the
 * same OCR output twice: agreeing with itself proves nothing, and the only
 * honest answer would have been "unsure" every time.
 *
 * The page alone was still not enough. Asked whether "100.00" matches a budget
 * table holding hundreds of figures, a reviewer has to find it first, and that
 * is not the question being put to them. So the value's own words are marked on
 * the page, and a crop of that region is served separately as the thing to
 * actually read.
 *
 * The page is rendered once and cached; marks are drawn per request, because
 * they differ per value while the page does not.
 */
class ReviewPageImageController extends Controller
{
    public function __invoke(
        Request $request,
        ExtractionField $field,
        ArtifactWorkspace $workspace,
        PageRasterizer $rasterizer,
        ValueLocator $locator,
        EvidenceImagePainter $painter,
    ): Response {
        abort_unless($request->user()?->can('viewAny', SourcePublisher::class) === true, 403);

        $cacheKey = 'review-pages/'.$field->extraction_page_id.'.png';
        $disk = Storage::disk('local');
        $wantsCrop = $request->boolean('crop');

        if ($disk->exists($cacheKey)) {
            $cached = (string) $disk->get($cacheKey);

            return $this->respond($cached, $field, $locator, $painter, $wantsCrop);
        }

        $artifact = $field->run?->artifactVersion;

        abort_unless($artifact instanceof SourceArtifactVersion, 404, 'This value has no source document.');

        $materialized = null;
        $image = null;

        try {
            $materialized = $workspace->materialize($artifact);
            $image = $rasterizer->rasterize(
                $materialized,
                max(1, (int) $field->evidence_page_number),
                (int) config('civiclens.extraction.review_page_dpi', 150),
            );

            $png = file_get_contents($image);

            abort_if($png === false, 404, 'The page could not be rendered.');

            $disk->put($cacheKey, $png);

            return $this->respond($png, $field, $locator, $painter, $wantsCrop);
        } catch (Throwable) {
            // A page that cannot be rendered is not an error worth breaking the
            // queue over; the screen says so and the reviewer moves on.
            abort(404, 'The page could not be rendered.');
        } finally {
            if ($image !== null) {
                $rasterizer->discard($image);
            }

            $workspace->discard($materialized);
        }
    }

    private function respond(
        string $png,
        ExtractionField $field,
        ValueLocator $locator,
        EvidenceImagePainter $painter,
        bool $wantsCrop,
    ): Response {
        $page = $field->page;
        $boxes = $page instanceof ExtractionPage ? $locator->locate($field, $page) : [];

        try {
            if ($boxes === []) {
                // Nothing located means the page was read before word geometry
                // was stored. The whole page is still the honest thing to show,
                // and the screen says the value was not pinpointed.
                return response($png, 200, ['Content-Type' => 'image/png']);
            }

            $marked = $wantsCrop ? $painter->crop($png, $boxes[0]) : $painter->highlight($png, $boxes);
        } catch (Throwable) {
            return response($png, 200, ['Content-Type' => 'image/png']);
        }

        return response($marked, 200, ['Content-Type' => 'image/png']);
    }
}
