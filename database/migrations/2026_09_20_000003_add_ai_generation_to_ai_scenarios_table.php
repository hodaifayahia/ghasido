<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A whole role-play scenario can be drafted by the AI (GEN-01, RP-01). The
 * draft lands in `ai_draft` and is applied to the columns explicitly, never
 * auto-published (GEN-03); `ai_status` drives the CMS poll (PERF-04).
 *
 * json + string columns (never enum()) so the migration runs on SQLite and
 * MySQL alike (AGENTS.md §6); the model casts ai_status to GenerationStatus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_scenarios', function (Blueprint $table): void {
            $table->json('ai_draft')->nullable();
            $table->string('ai_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_scenarios', function (Blueprint $table): void {
            $table->dropColumn(['ai_draft', 'ai_status']);
        });
    }
};
