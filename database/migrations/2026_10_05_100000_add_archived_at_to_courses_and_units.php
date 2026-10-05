<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Delete" on a course or unit (client request 2026-10-05, CMS-01). One that
 * still holds a lesson learners answered, or a certificate, leaves the admin
 * library and every learner but keeps its row, so every answer still resolves
 * to the content it referred to (DATA-10, DATA-11). Same mechanism as lessons
 * and scenarios: an explicit timestamp, no SoftDeletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable();
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });
    }
};
