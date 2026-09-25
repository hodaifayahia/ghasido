<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A lesson can be drafted by the AI (GEN-01). `ai_status` walks the same
 * pending -> running -> done|failed states as a lexicon draft so the CMS can
 * tell a lesson that is still being written from one that is ready (PERF-04).
 *
 * String column, not enum(), so it runs on SQLite and MySQL alike (AGENTS.md
 * §6); the model casts it to App\Enums\GenerationStatus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->string('ai_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn('ai_status');
        });
    }
};
