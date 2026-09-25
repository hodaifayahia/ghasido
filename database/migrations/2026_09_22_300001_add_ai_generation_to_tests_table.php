<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI question generation on a test (GEN-01, GEN-03, GEN-04, PERF-04).
 *
 * `ai_status` is the queued job's state the admin page polls; `ai_request`
 * keeps what was asked (skills, level, notes, paired source) and which
 * placements the last run created, so Regenerate can replace exactly that
 * batch. Strings, not enums, so the same migration runs on SQLite and MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->string('ai_status')->nullable();
            $table->text('ai_failed_reason')->nullable();
            $table->json('ai_request')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->dropColumn(['ai_status', 'ai_failed_reason', 'ai_request']);
        });
    }
};
