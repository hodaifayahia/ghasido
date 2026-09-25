<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every metered call to an AI, TTS or STT provider (AIL-04, API-03,
 * spec 0003 B.6).
 *
 * One row per call, written by App\Services\Ai\UsageMeter, so per-employee
 * and per-hotel limits are counted live from here (AIL-01..03) and the
 * Super Admin can see what the platform is spending. Both owner columns are
 * nullable: an admin generating lexicon drafts belongs to no hotel, and a
 * seeder run has no user.
 *
 * Write once: `created_at` only, `occurred_at` is the moment of the call.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature')->index();
            $table->string('provider');
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->decimal('cost_estimate', 10, 6)->default(0);
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['hotel_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
