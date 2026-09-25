<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-tests and Post-tests (TEST-01, TEST-02, TSTM-02, TSTM-03, spec 0003 B.6).
 *
 * A test is a paired assessment: one row per type, joined through
 * `paired_test_id`. Its questions are `activity_placements` rows pointing at
 * this table, so a question is the same kind of thing as a lesson practice
 * item (PRAC-05) and every answer lands in `attempts` with a version (TEST-09).
 *
 * `settings` holds what the admin configures and the runner reads: the time
 * limit, shuffling, result visibility, pass score and timeout behaviour
 * (TEST-04, TIME-01). None of it is a constant anywhere else.
 *
 * `type` and `status` are strings cast to enums, never $table->enum(): the same
 * migration runs on SQLite in CI and MySQL 8.4 locally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            // Null = shared across hotels (ORG-04, CMS-04).
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('paired_test_id')->nullable()->constrained('tests')->nullOnDelete();
            $table->string('title');
            $table->json('intro')->nullable();
            $table->json('settings')->nullable();
            $table->string('status')->index();
            $table->timestamps();

            $table->index(['department_id', 'hotel_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tests');
    }
};
