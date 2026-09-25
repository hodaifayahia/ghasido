<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every answer an employee gives, practice or test (TEST-06, DATA-01, PRAC-04,
 * spec 0003 B.6).
 *
 * This is the research instrument. `raw_answer` is always written; a score
 * alone is a defect. The row points at the exact `activity_versions` row it
 * answered, so an edit to a question later never rewrites history (TEST-09,
 * DATA-11). `time_taken_ms` is recorded whether or not a timer was configured
 * (TIME-04, DATA-08).
 *
 * unique(test_attempt_id, activity_id) makes a test answer an upsert: one row
 * per question per sitting, so a retried save cannot duplicate it (PERF-03).
 * NULL repeats in a unique index on both engines, so practice rows (no test
 * attempt) are unconstrained and may accumulate attempt_no 1, 2, 3 (PRAC-07).
 *
 * On delete: the answer-holding side is protected. A hard delete of an
 * activity or version is refused while answers exist (restrict), and the
 * nullable references to builder-managed rows (placement, block, lesson,
 * test attempt) null out rather than cascade, so a block removed in the CMS
 * never erases an answer (DATA-10). The spec's cascade default applies only to
 * the owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('placement_id')->nullable()->constrained('activity_placements')->nullOnDelete();
            $table->foreignId('test_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('block_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('attempt_no')->default(1);

            $table->json('raw_answer')->nullable();
            $table->json('answer_history')->nullable();
            $table->foreignId('response_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->text('transcript')->nullable();

            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('max_score', 5, 2)->nullable();
            $table->json('ai_feedback')->nullable();
            $table->string('ai_status')->nullable();
            $table->foreignId('score_overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('original_score', 5, 2)->nullable();

            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('time_taken_ms')->nullable();
            $table->timestamps();

            $table->unique(['test_attempt_id', 'activity_id']);
            $table->index(['user_id', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};
