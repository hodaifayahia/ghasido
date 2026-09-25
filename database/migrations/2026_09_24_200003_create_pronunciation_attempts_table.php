<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One pronunciation check of one recording (spec 0006 §5, §6).
 *
 * Research data (DATA-01, DATA-02, DATA-08): the raw STT words of both
 * listens, the per-word verdicts, the scores and the scoring version are
 * all kept, so a later scoring rule can re-score without re-recording.
 * Content foreign keys null on delete: removing a block never removes an
 * answer (DATA-10). No soft deletes, nothing is ever pruned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pronunciation_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('block_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lexicon_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('item_index')->nullable();
            $table->unsignedSmallInteger('word_index')->nullable();
            $table->foreignId('voice_recording_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reference_text');
            // The whole sentence when reference_text is one word of it (a drill).
            $table->text('sentence_text')->nullable();
            $table->string('accent', 5);
            $table->unsignedInteger('attempt_no')->default(1);
            $table->string('status', 16)->default('pending');
            $table->text('failed_reason')->nullable();
            $table->string('stt_provider')->nullable();
            $table->string('stt_model')->nullable();
            $table->json('stt_raw')->nullable();
            $table->json('words')->nullable();
            $table->json('extras')->nullable();
            $table->unsignedSmallInteger('fillers')->default(0);
            $table->unsignedSmallInteger('long_pauses')->default(0);
            // Learner's speaking time ÷ the reference audio's (1.0 = same pace).
            $table->decimal('speed_ratio', 5, 2)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('words_score', 5, 2)->nullable();
            $table->decimal('clarity_score', 5, 2)->nullable();
            $table->decimal('flow_score', 5, 2)->nullable();
            $table->string('level', 16)->nullable();
            $table->string('scoring_version', 16)->nullable();
            $table->json('feedback')->nullable();
            $table->string('feedback_status', 16)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('coached_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['block_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pronunciation_attempts');
    }
};
