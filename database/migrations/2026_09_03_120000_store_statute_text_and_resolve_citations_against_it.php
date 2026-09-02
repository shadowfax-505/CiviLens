<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somewhere to put statute text, and the row that says which provision a
 * citation was matched to.
 *
 * 7,874 statutory references have been read off High Court judgments — 7,780 of
 * them the Supreme Court's English stratum — and every one is a string. "Section
 * 410 of the Code of Criminal Procedure" is only worth more than its characters
 * once there is a Code of Criminal Procedure to look section 410 up in, and
 * these are the tables that hold one.
 *
 * **Nothing here carries a bound, and there is deliberately no column that could
 * be mistaken for one.** No decision, no alpha, no basis. ADR-024 records why:
 * the stratum `supremecourt-bangladesh | en | statutory_reference` holds 7,780
 * values and has been judged zero times, so no threshold has ever been fitted
 * for it and deny-by-default refuses every one. A resolution is a match between
 * two strings. Putting it in its own table rather than in a column on
 * `extraction_fields` is most of what keeps that true: the row that decides what
 * publishes is not written by this layer at all, and a reader looking at a
 * resolution cannot find a certification next to it.
 *
 * Nothing here fetches. `source_url` and `retrieved_at` stay null until a
 * publisher of statute text is registered with a robots and terms decision
 * recorded against it, which is a governance step and not this one. ADR-020's
 * discipline applies to the gap as much as to anything else: the columns exist
 * and say plainly that they hold nothing yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutes', function (Blueprint $table): void {
            $table->id();

            // What the parser keys on: 'code-of-civil-procedure', 'constitution'.
            // Year-free by construction, because only 427 of 7,874 citations
            // state a year at all — the candidate pattern's comma lookahead cuts
            // "Code of Criminal Procedure, 1898" short of it — so a key carrying
            // one would split an instrument in two and match neither half.
            $table->string('instrument_key', 160)->unique();

            $table->string('title');

            // Disambiguates instruments that share a title, which is the only
            // thing a citation's year is good for here.
            $table->unsignedSmallInteger('instrument_year')->nullable();
            $table->string('instrument_number', 64)->nullable();

            // Every other key this instrument is cited by, as data rather than
            // as a table inside the parser. The corpus writes the Code of Civil
            // Procedure as 'cpc' 26 times, 'c-p-c' twice and 'crpc' — a
            // different Code — 27 times, and each of those is a judgement about
            // Bangladeshi practice that belongs where a person can read and
            // correct it. A parser that expanded them would be guessing in a
            // place nobody could inspect.
            $table->json('known_as')->nullable();

            $table->string('script', 16)->default('en');

            // Null until a registered endpoint fills them. See the class note.
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('retrieved_at')->nullable();

            $table->timestamps();

            $table->index('instrument_year');
        });

        Schema::create('statute_provisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statute_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->string('provision_type', 24);

            // As the instrument writes it, letter suffix included: '5', '265H'.
            $table->string('provision_number', 24);

            // As a citation states it: '5(2)', '102(2)(a)(ii)'.
            $table->string('provision_label', 64);

            // Flattened and case-folded for matching: 'article-102-2-a-ii'. The
            // corpus writes the same transliterated Bengali clause as '(ka)' 27
            // times and '(Ka)' 21, and those are one provision.
            $table->string('provision_key', 96);

            $table->string('heading')->nullable();
            $table->longText('text')->nullable();
            $table->char('text_sha256', 64)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->unsignedInteger('position')->nullable();
            $table->timestamps();

            $table->unique(['statute_id', 'provision_key']);
            $table->index('provision_key');
        });

        Schema::create('statutory_citation_resolutions', function (Blueprint $table): void {
            $table->id();

            // One resolution per value, replaced on a re-parse rather than
            // accumulated. A judgement about a value is history worth keeping
            // and is kept elsewhere; a parse is a pure function of the string
            // and the parser version, so a second row for the same pair would
            // record nothing a re-run could not reproduce.
            $table->foreignId('extraction_field_id')->unique()->constrained()->cascadeOnDelete();

            // The characters this parse was taken from. A re-extraction can
            // change a value under its resolution, and a match against
            // characters that are no longer there is a match to the wrong
            // string — the same reason a judgement stores what it judged.
            $table->text('raw_value');

            // Which reader produced it. `civiclens:health` reports a re-score is
            // owed when more than one reader's scores are on record, because a
            // measurement against characters that have since changed is a
            // measurement of the wrong thing. A parse is in the same position.
            $table->string('parser_version', 48);

            $table->string('instrument_name')->nullable();
            $table->string('instrument_key', 160)->nullable();
            $table->unsignedSmallInteger('instrument_year')->nullable();
            $table->string('provision_type', 24)->nullable();
            $table->string('provision_number', 24)->nullable();
            $table->string('provision_label', 64)->nullable();
            $table->json('sub_provisions')->nullable();
            $table->string('match_key', 255)->nullable();

            // Why the parser declined, when it did: no_provision,
            // no_instrument, anaphoric_instrument, unnamed_instrument. An
            // unparsed citation is a fact worth having — 912 of these values
            // cite "the Code" and there are two of those in the same documents —
            // and a row saying so is how that fact stays countable instead of
            // becoming a silent gap between 7,874 and whatever parsed.
            $table->string('reject_reason', 32)->nullable();

            $table->foreignId('statute_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('statute_provision_id')->nullable()->constrained()->nullOnDelete();

            // unparsed | unmatched | instrument | provision. Four states rather
            // than a boolean, because "we hold this Act and not this section of
            // it" and "we have never held this Act" are different answers and
            // the second is the one that says what to go and fetch.
            $table->string('match_status', 16)->default('unparsed');
            $table->timestamp('matched_at')->nullable();

            $table->timestamps();

            $table->index(['match_status', 'instrument_key']);
            $table->index('match_key');
            $table->index(['reject_reason', 'match_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_citation_resolutions');
        Schema::dropIfExists('statute_provisions');
        Schema::dropIfExists('statutes');
    }
};
