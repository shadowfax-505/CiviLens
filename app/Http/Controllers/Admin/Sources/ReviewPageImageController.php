<?php

namespace App\Http\Controllers\Admin\Sources;

use App\Http\Controllers\Controller;
use App\Models\ExtractionField;
use App\Models\SourceArtifactVersion;
use App\Models\SourcePublisher;
use App\Services\Extraction\ArtifactWorkspace;
use App\Services\Extraction\PageRasterizer;
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
 * Rendered once and cached. Rasterising a page takes long enough that doing it
 * on every view would make adjudication slow, and slow is what stops fifty
 * reviews from happening.
 */
class ReviewPageImageController extends Controller
{
    public function __invoke(
        Request $request,
        ExtractionField $field,
        ArtifactWorkspace $workspace,
        PageRasterizer $rasterizer,
    ): Response {
        abort_unless($request->user()?->can('viewAny', SourcePublisher::class) === true, 403);

        $cacheKey = 'review-pages/'.$field->extraction_page_id.'.png';
        $disk = Storage::disk('local');

        if ($disk->exists($cacheKey)) {
            return response($disk->get($cacheKey), 200, ['Content-Type' => 'image/png']);
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

            return response($png, 200, ['Content-Type' => 'image/png']);
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
}
