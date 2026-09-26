<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The Arabic meaning of any English text on the platform — a course title,
 * a unit, a lesson objective, a question — kept once per distinct text and
 * reused everywhere it appears (CTRL-01..03; client decision 2026-09-26).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('text_translations', function (Blueprint $table): void {
            $table->id();
            $table->string('hash', 64)->unique();
            $table->text('source_text');
            $table->text('arabic')->nullable();
            $table->string('status')->default('pending');
            $table->string('source')->default('ai');
            $table->string('failed_reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('text_translations');
    }
};
