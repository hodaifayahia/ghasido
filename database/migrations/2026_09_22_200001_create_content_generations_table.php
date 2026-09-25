<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per "Generate with AI" request: a whole lesson, a whole course, or
 * one image (GEN-01, GEN-03, GEN-04, PERF-04; spec 0004).
 *
 * The queued jobs write their progress here and the Lessons & Content screen
 * polls it, so every generation ends in `done` or `failed` with a reason the
 * admin can read (PERF-04). Enumerated columns are strings cast to enums in
 * the model, so the table migrates on SQLite and MySQL alike (AGENTS.md §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->text('prompt');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level')->nullable();
            $table->json('options')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('stage')->nullable();
            $table->text('failed_reason')->nullable();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->json('outline')->nullable();
            $table->json('lesson_ids')->nullable();
            $table->unsignedInteger('lessons_total')->default(0);
            $table->unsignedInteger('lessons_done')->default(0);
            $table->unsignedInteger('images_total')->default(0);
            $table->unsignedInteger('images_done')->default(0);
            $table->unsignedInteger('images_failed')->default(0);
            $table->json('audio_texts')->nullable();
            $table->json('target')->nullable();
            $table->foreignId('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_generations');
    }
};
