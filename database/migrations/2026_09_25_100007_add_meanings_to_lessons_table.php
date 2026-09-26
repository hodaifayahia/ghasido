<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Show Meaning on the lesson's own text (CTRL-01..03; user request
 * 2026-09-25): the Arabic meaning and a simple explanation of the title,
 * the introduction and each objective, keyed by field:
 *
 *     {"title": {"arabic": "…", "explanation": "…"},
 *      "introduction": {…}, "objectives:0": {…}}
 *
 * Block copy (subtitle, tip, quote…) keeps the same map in its settings
 * json under `meanings`, so it needs no column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->json('meanings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn('meanings');
        });
    }
};
