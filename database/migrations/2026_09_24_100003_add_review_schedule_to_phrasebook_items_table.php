<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spaced review of My Phrasebook (PHRASE-01..05; spec 0005 §3.4).
 *
 * A Leitner schedule on each saved item: `review_box` 0-5 picks the next
 * interval, `due_at` is when the card comes back, and the counters let the
 * learner (and the research export) see how often a phrase was missed.
 * A new item is due at once (null `due_at`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phrasebook_items', function (Blueprint $table): void {
            $table->unsignedTinyInteger('review_box')->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('lapse_count')->default(0);
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamp('due_at')->nullable();

            $table->index(['user_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('phrasebook_items', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'due_at']);
            $table->dropColumn(['review_box', 'review_count', 'lapse_count', 'last_reviewed_at', 'due_at']);
        });
    }
};
