<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One complete AI role-play conversation (RP-05, RP-11, DATA-05, spec 0003 B.6).
 *
 * One attempt is one whole conversation, never one message (RP-06). The full
 * transcript is stored turn by turn on every attempt, and the evaluation lands
 * as structured criterion scores plus feedback blocks (AIE-04), so it exports
 * to long format. `is_preview` marks an admin test run that never counts
 * against an employee and never appears in an export (RP-13).
 *
 * Deleting a scenario is refused while attempts exist (restrict, DATA-10);
 * the lesson and block references null out so a builder change never erases
 * a transcript.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roleplay_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_scenario_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('block_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->string('status')->index();

            $table->json('transcript');
            $table->boolean('pending_reply')->default(false);

            $table->json('criteria_scores')->nullable();
            $table->unsignedSmallInteger('overall_score')->nullable();
            $table->json('feedback')->nullable();
            $table->string('ai_status')->nullable();
            $table->text('failed_reason')->nullable();

            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ai_scenario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roleplay_attempts');
    }
};
