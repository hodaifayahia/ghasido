<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The learner's measured English level (spec 0005 §2.1): set from their
 * latest submitted Pre- or Post-test and read by the AI so a role-play guest
 * speaks at the learner's level. A string column cast to App\Enums\EnglishLevel
 * (portable across SQLite and MySQL, AGENTS.md §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('english_level', 20)->nullable();
            $table->timestamp('english_level_assessed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['english_level', 'english_level_assessed_at']);
        });
    }
};
