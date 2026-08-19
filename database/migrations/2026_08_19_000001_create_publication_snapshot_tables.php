<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The set of things CivicLens is prepared to publish, frozen at a moment.
 *
 * The pipeline's own tables answer "what do we currently believe"; they change
 * whenever a page is re-extracted or a threshold moves. A public site and a
 * paper need the other question answered — "what did we publish, and on what
 * basis" — and it has to stay answerable after the pipeline has moved on.
 *
 * So a snapshot copies the values it publishes rather than pointing at them.
 * The ids are kept alongside so a row can always be traced back, but the
 * published value, the level it was certified at, and the basis of that
 * certification are stored, because those are exactly the things that would
 * otherwise be silently rewritten under a citation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();

            // The risk level asked for. What each group was actually certified
            // at can be looser, and is recorded per figure.
            $table->float('requested_alpha');

            // Identifies the calibration these decisions came from, so a
            // snapshot built after recalibration is distinguishable from one
            // built before it even when the counts happen to match.
            $table->string('calibration_digest', 64)->nullable();

            $table->unsignedInteger('document_count')->default(0);
            $table->unsignedInteger('figure_count')->default(0);
            $table->unsignedInteger('notice_count')->default(0);

            // Accepted fields that could not be traced to a document. Counted
            // rather than dropped quietly: a snapshot that publishes less than
            // it should must say so on its face.
            $table->unsignedInteger('skipped_figure_count')->default(0);

            $table->text('notes')->nullable();
            $table->timestamp('built_at');

            // Null until an operator publishes it. Building is not publishing.
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('published_at');
        });

        Schema::create('publication_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('publication_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_artifact_version_id')->constrained();

            $table->string('publisher_slug');
            $table->string('publisher_name');
            $table->string('attribution_name')->nullable();
            $table->string('rights_decision')->nullable();

            $table->text('source_url');
            $table->string('original_filename')->nullable();
            $table->string('media_type');
            $table->unsignedBigInteger('byte_size');
            $table->string('sha256', 64);
            $table->unsignedInteger('page_count')->default(0);

            // Zero is a real answer and stays visible. Most archived artifacts
            // are web pages that certify nothing, and a corpus that hid them
            // would look like it certifies everything it holds.
            $table->unsignedInteger('figure_count')->default(0);

            // Whether this is a published document or an archived web page or
            // record. The distinction is load-bearing: 476 artifacts is not
            // 476 documents, and conflating them overstates the corpus.
            $table->boolean('is_document')->default(true);
            $table->timestamp('retrieved_at')->nullable();
            $table->timestamps();

            $table->unique(['publication_snapshot_id', 'source_artifact_version_id'], 'pub_doc_snapshot_artifact_unique');
        });

        Schema::create('publication_figures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('publication_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('publication_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extraction_field_id')->constrained();

            // What the pattern claimed the value is — amount, date, reference.
            // Kept separate from the storage type, which is always a string and
            // tells a reader nothing.
            $table->string('field_kind');
            $table->string('field_type');
            $table->text('extracted_value');
            $table->text('normalized_value')->nullable();
            $table->unsignedInteger('page_number');
            $table->string('script_class')->nullable();

            // ADR-018: the level travels with the group everywhere it is
            // reported. A figure certified at 0.10 is not comparable with one
            // certified at 0.05, so neither is ever shown without its level.
            $table->float('certified_alpha')->nullable();
            $table->string('decision_basis')->nullable();
            $table->string('publisher_group')->nullable();
            $table->float('nonconformity_score')->nullable();
            $table->timestamps();

            $table->unique(['publication_snapshot_id', 'extraction_field_id'], 'pub_fig_snapshot_field_unique');
            $table->index(['publication_snapshot_id', 'field_kind']);
        });

        Schema::create('publication_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('publication_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tender_observation_id')->constrained();

            $table->string('publisher_slug');
            $table->string('external_id')->nullable();
            $table->text('reference_number')->nullable();
            $table->text('status')->nullable();
            $table->string('procurement_nature')->nullable();
            $table->timestamp('published_on')->nullable();
            $table->text('source_url')->nullable();
            $table->timestamps();

            $table->unique(['publication_snapshot_id', 'tender_observation_id'], 'pub_notice_snapshot_observation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_notices');
        Schema::dropIfExists('publication_figures');
        Schema::dropIfExists('publication_documents');
        Schema::dropIfExists('publication_snapshots');
    }
};
