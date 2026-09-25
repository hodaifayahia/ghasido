<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The accent a lesson is taught in, and one voice per accent (spec 0006 §3).
 *
 * `lessons.accent` is a BCP 47 tag (App\Enums\Accent); null keeps today's
 * behaviour: the accent and voice of the platform voice. The two voice
 * columns are nullable too: null falls back to the platform voice when its
 * accent matches, else to the accent's default voice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->string('accent', 5)->nullable();
        });

        Schema::table('tts_settings', function (Blueprint $table): void {
            $table->string('british_voice')->nullable();
            $table->string('american_voice')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn('accent');
        });

        Schema::table('tts_settings', function (Blueprint $table): void {
            $table->dropColumn(['british_voice', 'american_voice']);
        });
    }
};
