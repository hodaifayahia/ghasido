<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why an answer's AI job (transcription or evaluation) failed, so the admin
 * sees a terminal state with a reason instead of a spinner (PERF-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->text('ai_failed_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn('ai_failed_reason');
        });
    }
};
