<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a role-play conversation was held: `text` (chat, with optional voice
 * notes) or `voice_call` (live Deepgram Voice Agent). Reports and exports
 * can tell the two apart (RP-11, DATA-05; spec 0004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roleplay_attempts', function (Blueprint $table): void {
            $table->string('channel')->default('text');
        });
    }

    public function down(): void
    {
        Schema::table('roleplay_attempts', function (Blueprint $table): void {
            $table->dropColumn('channel');
        });
    }
};
