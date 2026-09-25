<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-scenario authoring controls used by the settings rail (RP-05, AIE-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_scenarios', function (Blueprint $table): void {
            $table->json('settings')->nullable()->after('feedback_criteria');
        });
    }

    public function down(): void
    {
        Schema::table('ai_scenarios', function (Blueprint $table): void {
            $table->dropColumn('settings');
        });
    }
};
