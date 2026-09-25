<?php

use App\Enums\GenerationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One generated audio file per (text, voice, speed) (CTRL-05, TTS-01, TTS-02,
 * spec 0003 B.4).
 *
 * Audio is synthesised once, in a queued job, and served statically; playing
 * a sentence never triggers synthesis. The unique index is what makes the
 * same sentence reused in two lessons resolve to one file.
 *
 * `text_hash` is sha256 of the trimmed, whitespace-collapsed text
 * (AudioClip::hashFor), so a stray double space cannot fork a clip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_clips', function (Blueprint $table) {
            $table->id();
            $table->text('text');
            $table->char('text_hash', 64);
            $table->string('voice');
            $table->string('speed');
            // nullOnDelete: losing the file must not lose the row, so the
            // clip can be regenerated (TTS-03).
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('status')->default(GenerationStatus::Pending->value);
            $table->text('failed_reason')->nullable();
            $table->string('provider')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['text_hash', 'voice', 'speed']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_clips');
    }
};
