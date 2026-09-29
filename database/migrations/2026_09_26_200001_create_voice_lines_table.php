<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The stored guest lines of the fast voice engine (spec 0009): every line
 * the AI guest says in a live call is recorded once, and the AI may reuse a
 * recorded line instead of writing and voicing a new one (TTS-02, AIL-04).
 *
 * The audio itself is an ordinary `audio_clips` row, so a line shared by two
 * scenarios is one file. A row with no scenario is a generic line
 * ("Sorry, could you say that again?") offered in every scenario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_scenario_id')->nullable()->constrained('ai_scenarios')->cascadeOnDelete();
            $table->string('voice');
            $table->text('text');
            $table->char('text_hash', 64);
            $table->foreignId('audio_clip_id')->nullable()->constrained('audio_clips')->nullOnDelete();
            // Offered to the AI for reuse. False for a line tied to one
            // conversation (a name or number the employee gave).
            $table->boolean('reusable')->default(true);
            $table->unsignedInteger('uses')->default(0);
            $table->unsignedInteger('reuses')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['ai_scenario_id', 'voice', 'text_hash']);
            $table->index(['voice', 'reusable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_lines');
    }
};
