<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A certificate issued to an employee for a course (CERT-02, CERT-06, CERT-07,
 * spec 0003 B.6).
 *
 * Revoking sets `revoked_at` and keeps the row: issue, revoke and re-issue
 * are separate rows, never an edit (CERT-06). `verification_id` is the public
 * identifier a lookup would use, kept apart from the primary key so the
 * database id is never printed on a document.
 *
 * Deleting a course is refused while certificates exist (restrict): an issued
 * document outlives a curriculum change (DATA-10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->uuid('verification_id')->unique();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
