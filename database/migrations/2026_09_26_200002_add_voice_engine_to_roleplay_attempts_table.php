<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which engine ran a live voice call (spec 0009): the Deepgram voice agent
 * bills the connected minutes, the fast engine bills streamed transcription
 * seconds plus stored speech, so the call is metered under a different
 * model. Null for text role-plays and for calls made before this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roleplay_attempts', function (Blueprint $table) {
            $table->string('voice_engine')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('roleplay_attempts', function (Blueprint $table) {
            $table->dropColumn('voice_engine');
        });
    }
};
