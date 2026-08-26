<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every judgement made about a value, rather than only the last one.
 *
 * The field carried one verdict, so a second reviewer could only overwrite the
 * first. That makes the most useful measurement this project could report
 * impossible to take: how often two people looking at the same scanned figure
 * agree. The bound is computed from human labels, and a bound is only as
 * trustworthy as the labels are reproducible — reporting a risk level without
 * ever having measured whether the labels themselves are stable is the largest
 * unexamined assumption in the method.
 *
 * The field keeps its own verdict exactly as before, set by whoever judged it
 * first, so calibration and everything computed from it are untouched. These
 * rows are the record a second opinion is written into.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extraction_field_judgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('extraction_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();

            // What the reviewer said, in their words rather than reduced to a
            // boolean: "cannot tell" is a real answer and is not "incorrect".
            $table->string('verdict');
            $table->text('note')->nullable();

            // What the value said when they judged it. Re-extraction can change
            // a value under a judgement, and a label about characters that are
            // no longer there is worse than no label.
            $table->text('value_judged');
            $table->timestamps();

            $table->unique(['extraction_field_id', 'user_id']);
            $table->index('verdict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_field_judgements');
    }
};
