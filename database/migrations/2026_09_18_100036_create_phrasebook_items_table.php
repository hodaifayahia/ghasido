<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * My Phrasebook: the words and expressions an employee saved (PHRASE-01,
 * PHRASE-02, DATA-09, spec 0003 B.6).
 *
 * Two shapes share the table. A saved lexicon item points at `lexicon_items`;
 * a phrase saved from a dialogue line or a free sentence keeps its own text and
 * Arabic, with `custom_hash` (sha256 of the normalised text) so the same
 * sentence saved twice is one row. The two unique keys make both saves
 * idempotent; NULL repeats in a unique index on both engines, so each key only
 * bites its own shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phrasebook_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lexicon_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('custom_text')->nullable();
            $table->text('custom_arabic')->nullable();
            $table->char('custom_hash', 64)->nullable();
            $table->foreignId('source_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->timestamp('saved_at');
            $table->timestamps();

            $table->unique(['user_id', 'lexicon_item_id']);
            $table->unique(['user_id', 'custom_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phrasebook_items');
    }
};
