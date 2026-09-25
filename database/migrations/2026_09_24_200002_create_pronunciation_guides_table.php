<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the platform knows about how one text should sound in one accent
 * (spec 0006 §4): the Qwen-drafted guide (IPA, syllables, trap words,
 * homophones) and the calibration measured on our own reference audio.
 *
 * Keyed like audio clips — sha256 of the normalised text — so a sentence
 * shared by several lessons of the same accent has one guide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pronunciation_guides', function (Blueprint $table): void {
            $table->id();
            $table->text('text');
            $table->char('text_hash', 64);
            $table->string('accent', 5);
            $table->string('status', 16)->default('pending');
            $table->text('failed_reason')->nullable();
            // ai | manual | edited: an edited guide is never overwritten by a regeneration.
            $table->string('source', 16)->default('ai');
            $table->text('ipa')->nullable();
            $table->json('words')->nullable();
            $table->json('tips')->nullable();
            $table->json('calibration')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['text_hash', 'accent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pronunciation_guides');
    }
};
