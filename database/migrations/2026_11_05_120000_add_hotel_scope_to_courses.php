<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hotel groups for shared courses (client request 2026-11-05): besides
 * "all hotels" and "one hotel", a shared course can be shown to only a
 * chosen group of hotels, or to every hotel except a chosen group.
 *
 * - `courses.hotel_scope`: all | only | except (string + enum cast, so it
 *   migrates on SQLite and MySQL alike). Existing courses stay `all`.
 * - `course_hotel`: the chosen group.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('hotel_scope', 10)->default('all');
        });

        Schema::create('course_hotel', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'hotel_id']);
            $table->index('hotel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_hotel');

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn('hotel_scope');
        });
    }
};
