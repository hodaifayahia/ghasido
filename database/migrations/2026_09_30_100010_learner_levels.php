<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Learner levels (client decision 2026-09-30).
 *
 * - The three levels become Beginner, Intermediate and Advanced. Stored
 *   values move up one name so their order is kept: intermediate becomes
 *   advanced, then elementary becomes intermediate.
 * - Courses (and so their lessons) and tests get a level; null means every
 *   level, as a null department means every department.
 * - The learner keeps a pending "move up" suggestion after a strong
 *   Pre-test until they choose to move or stay.
 * - platform_settings: small Super Admin settings, first the level-up
 *   threshold (Settings → Learning).
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const LEVEL_COLUMNS = [
        'users' => ['english_level'],
        'attempts' => ['graded_level'],
        'roleplay_attempts' => ['graded_level'],
        'ai_scenarios' => ['difficulty'],
        'content_generations' => ['level'],
    ];

    public function up(): void
    {
        foreach (self::LEVEL_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)->where($column, 'intermediate')->update([$column => 'advanced']);
                DB::table($table)->where($column, 'elementary')->update([$column => 'intermediate']);
            }
        }

        Schema::table('courses', function (Blueprint $table): void {
            $table->string('level', 20)->nullable()->index();
        });

        Schema::table('tests', function (Blueprint $table): void {
            $table->string('level', 20)->nullable()->index();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('level_suggestion', 20)->nullable();
        });

        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('level_suggestion');
        });

        Schema::table('tests', function (Blueprint $table): void {
            $table->dropIndex(['level']);
            $table->dropColumn('level');
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropIndex(['level']);
            $table->dropColumn('level');
        });

        foreach (self::LEVEL_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)->where($column, 'intermediate')->update([$column => 'elementary']);
                DB::table($table)->where($column, 'advanced')->update([$column => 'intermediate']);
            }
        }
    }
};
