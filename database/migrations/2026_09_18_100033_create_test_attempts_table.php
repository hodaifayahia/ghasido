<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One sitting of a Pre-test or Post-test (TEST-05, TIME-05, spec 0003 B.6).
 *
 * `started_at` and `deadline_at` are written from the server clock when the
 * attempt is created, so a refresh or a device switch can never reset the
 * timer (TIME-05, AUTH-09). The per-question answers live in `attempts`,
 * keyed on this row; only the totals are kept here (TEST-06).
 *
 * Deleting a Test is refused while sittings exist: research data is never
 * erased by a content change (DATA-10). Content goes through `status`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->string('status')->index();
            $table->timestamp('started_at');
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('max_score', 5, 2)->nullable();
            $table->json('breakdown')->nullable();
            $table->timestamp('results_released_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'test_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_attempts');
    }
};
