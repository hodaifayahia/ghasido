<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per step an employee has finished (PROG-01, PROG-03, spec 0003 B.6).
 *
 * Written on every step completion, never only at lesson end. The unique key
 * makes the write idempotent: pressing Next twice, or a retried request after
 * a dropped connection, leaves one row (PROG-04).
 *
 * No timestamps: `completed_at` is the only moment that matters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');

            $table->unique(['user_id', 'block_id']);
            $table->index(['user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_completions');
    }
};
