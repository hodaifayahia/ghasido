<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Deepgram Voice Agent controls for the live spoken role-play
 * (RP-03, RP-04, API-04; spec 0004). One row, like tts_settings. Keys stay
 * in server-side environment configuration (API-02, SEC-03); `values` only
 * holds the safe controls the Super Admin edits in the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_agent_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('values')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_agent_settings');
    }
};
