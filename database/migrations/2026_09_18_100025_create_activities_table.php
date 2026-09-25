<?php

use App\Enums\ContentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One answerable item and its version history (PRAC-01..07, TEST-05, TEST-09,
 * DATA-11, WRITE-05, spec 0003 B.5).
 *
 * One activities table serves lessons and both tests alike; reuse is
 * expressed by activity_placements, never by duplicating the row (PRAC-05).
 *
 * Versioning is mandatory on anything answerable. Every create and every
 * change to `payload` or `scoring` writes an activity_versions row and bumps
 * `current_version` (Activity's model events); attempts reference the version
 * row, never the activity alone, and a version with attempts is never
 * mutated. activity_versions has `created_at` only: a version is written once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            // The chip above the question: "Situation", "Reading", ...
            $table->string('skill_label')->nullable();
            $table->string('title')->nullable();
            // The instruction line, e.g. "Look at the situation and choose
            // the best response."
            $table->text('prompt');
            $table->text('prompt_arabic')->nullable();
            $table->json('payload');
            $table->json('scoring')->nullable();
            $table->unsignedInteger('time_limit_seconds')->nullable();
            // 0 = unlimited (PRAC-07); tests apply their own single attempt.
            $table->unsignedSmallInteger('attempts_allowed')->default(0);
            $table->boolean('show_meaning_enabled')->default(true);
            $table->unsignedInteger('current_version')->default(1);
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status')->default(ContentStatus::Draft->value);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['department_id', 'hotel_id']);
        });

        Schema::create('activity_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('payload');
            $table->json('scoring')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['activity_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_versions');
        Schema::dropIfExists('activities');
    }
};
