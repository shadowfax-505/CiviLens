<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests to correct or withdraw something this site publishes.
 *
 * The corpus is audit reports, and audit reports name people. Most of those
 * names are officials acting in an official capacity — an auditor signing a
 * finding — which is ordinarily publishable. Some pages carry a phone number
 * beside a firm, or a letter-of-credit reference, and the pages are served here
 * as images that a search engine can reach and the original publisher's site
 * may not expose so plainly.
 *
 * That difference in reach is the thing a person can reasonably object to, and
 * publishing government records is not a reason to have no way of hearing it.
 *
 * The trail is the point. A request records what was asked, what was decided,
 * by whom and why — including refusals, because a route that only records the
 * requests it granted is a route that cannot be audited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correction_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();

            // What is being objected to. Free text rather than a foreign key: a
            // person objecting does not know this project's identifiers, and
            // demanding one would be a way of not hearing them.
            $table->text('about');
            $table->text('reason');
            $table->string('kind');

            // Optional. Someone may want a thing corrected without identifying
            // themselves, and requiring a name to be heard is its own privacy
            // problem.
            $table->string('contact')->nullable();

            $table->string('status')->default('received');
            $table->text('decision')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correction_requests');
    }
};
