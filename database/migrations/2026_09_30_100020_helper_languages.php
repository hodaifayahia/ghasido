<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Helper languages (client request 2026-09-30): Show Meaning is no longer
 * Arabic only. Arabic and French to start, and the Super Admin can add more
 * (Malay, Indonesian, Turkish…) without a developer.
 *
 * - text_translations holds one row per text and language: the old Arabic
 *   rows become `ar`, the text column is renamed from `arabic`.
 * - users.meaning_locale: the helper language the learner chose (null =
 *   the platform default the Super Admin sets).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('text_translations', function (Blueprint $table): void {
            $table->string('locale', 10)->default('ar')->after('hash');
        });

        Schema::table('text_translations', function (Blueprint $table): void {
            $table->dropUnique(['hash']);
            $table->unique(['hash', 'locale']);
            $table->renameColumn('arabic', 'translation');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('meaning_locale', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('meaning_locale');
        });

        // Only the Arabic rows fit the old one-row-per-text shape.
        DB::table('text_translations')->where('locale', '!=', 'ar')->delete();

        Schema::table('text_translations', function (Blueprint $table): void {
            $table->renameColumn('translation', 'arabic');
            $table->dropUnique(['hash', 'locale']);
            $table->unique('hash');
        });

        Schema::table('text_translations', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
