<?php

use App\Enums\ContentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A lesson: one learning session made of blocks (LESSON-01, CMS-05, spec 0003
 * B.5).
 *
 * `course_id` and `hotel_id` are denormalised from the unit's course and kept
 * in sync by Lesson's saving hook, so the learner scope and every report can
 * filter lessons without a two table join. The unit is the parent; the other
 * two columns are never written by hand.
 *
 * `completion_condition` is stored configuration, never a constant
 * (JOURNEY-04); null means "every visible block completed".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('introduction')->nullable();
            // A list of strings, e.g. ["Understand the situation", ...].
            $table->json('objectives')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->json('completion_condition')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('status')->default(ContentStatus::Draft->value);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'slug']);
            $table->index(['unit_id', 'position']);
            $table->index(['course_id', 'status']);
            $table->index('hotel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
