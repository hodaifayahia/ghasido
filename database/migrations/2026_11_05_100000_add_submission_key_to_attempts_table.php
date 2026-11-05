<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The key the practice page sends with one submission (client report
 * 2026-11-05; AGENTS.md §7, PROG-04, PERF-03).
 *
 * Every "Check" writes a new attempt row and never replaces an earlier one
 * (TEST-06, DATA-01). A retried request of the SAME submission (a double tap,
 * a dropped connection) carries the same key and finds the row it already
 * wrote instead of adding a second attempt. Unique per learner; NULL for
 * rows written before the key existed and for test answers (MySQL and
 * SQLite both allow repeated NULLs in a unique index).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->string('submission_key', 64)->nullable();
            $table->unique(['user_id', 'submission_key']);
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'submission_key']);
            $table->dropColumn('submission_key');
        });
    }
};
