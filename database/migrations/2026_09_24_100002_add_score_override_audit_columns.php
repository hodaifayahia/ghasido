<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An admin may override an AI score (AIE-05; spec 0005 §2.5).
 *
 * `attempts` already keeps the machine's value (`original_score`) and who
 * changed it (`score_overridden_by`); it gains the reason and the time. A
 * role-play attempt gains the same set for its overall score. The original
 * is never lost, so a research export can report both (REP-06).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table): void {
            $table->string('score_override_reason', 500)->nullable();
            $table->timestamp('score_overridden_at')->nullable();
        });

        Schema::table('roleplay_attempts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('original_overall_score')->nullable();
            $table->foreignId('score_overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('score_override_reason', 500)->nullable();
            $table->timestamp('score_overridden_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('roleplay_attempts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('score_overridden_by');
            $table->dropColumn(['original_overall_score', 'score_override_reason', 'score_overridden_at']);
        });

        Schema::table('attempts', function (Blueprint $table): void {
            $table->dropColumn(['score_override_reason', 'score_overridden_at']);
        });
    }
};
