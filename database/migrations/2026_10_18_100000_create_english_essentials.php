<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * English Essentials (client request 2026-10-18).
 *
 * - `courses.track`: `department` (the learner's department training, the
 *   "Training" stage of the journey) or `essentials` (beginner topics any
 *   learner opens when needed, never counted toward the certificate).
 * - `courses.department_id` becomes nullable: an Essentials topic belongs
 *   to no single department. Its audience is `course_department`; no row
 *   there means every department.
 * - `courses.icon`: the topic's icon on the learner's Essentials list.
 * - `blocks.help_lesson_id` / `help_prompt`: a step of a department lesson
 *   may point at one Essentials lesson ("Want to learn spelling? Tap
 *   here"); the learner comes back to the same step afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->unsignedBigInteger('department_id')->nullable()->change();
            $table->string('track')->default('department');
            $table->string('icon')->nullable();
            $table->index(['track', 'status']);
        });

        Schema::create('course_department', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'department_id']);
        });

        Schema::table('blocks', function (Blueprint $table): void {
            $table->foreignId('help_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->string('help_prompt', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('help_lesson_id');
            $table->dropColumn('help_prompt');
        });

        Schema::dropIfExists('course_department');

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropIndex(['track', 'status']);
            $table->dropColumn(['track', 'icon']);
        });

        // department_id stays nullable: an Essentials topic has none, and
        // choosing one for it on the way down would invent data.
    }
};
