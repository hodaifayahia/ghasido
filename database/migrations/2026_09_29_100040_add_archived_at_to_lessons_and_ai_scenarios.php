<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Delete" on a lesson or scenario that learners already answered removes it
 * from the admin library and from every learner, but keeps the row, its
 * blocks and every answer that points at it (CMS-01, DATA-10). The explicit
 * timestamp marks that removal; no SoftDeletes, so reports and exports still
 * resolve the content an answer referred to (DATA-11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable();
        });

        Schema::table('ai_scenarios', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });

        Schema::table('ai_scenarios', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });
    }
};
