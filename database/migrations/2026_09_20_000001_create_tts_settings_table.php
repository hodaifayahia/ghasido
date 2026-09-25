<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global lesson/scenario speech settings (TTS-04, API-04; spec 0003 Part C).
 * The provider key remains in server-side environment configuration (API-02);
 * this row only stores the safe controls an admin may change in the UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tts_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('voice')->nullable();
            $table->tinyInteger('expressivity')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tts_settings');
    }
};
