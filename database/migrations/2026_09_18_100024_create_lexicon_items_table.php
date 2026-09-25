<?php

use App\Enums\LexiconKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Words and expressions, with everything Show Meaning reveals (CMS-06,
 * CTRL-01..03, GEN-01, GEN-03, spec 0003 B.5).
 *
 * English is the base layer; the Arabic columns are only ever sent to the
 * client behind an explicit Show Meaning tap, and never inside a test
 * (CTRL-02, CTRL-04). `ai_draft` holds an AI generation the admin has not
 * accepted yet: nothing AI produced lands in the live columns on its own
 * (GEN-03).
 *
 * The pivot below is the ordered link from a vocabulary or expressions block
 * to its items. No timestamps: it is a pure ordering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lexicon_items', function (Blueprint $table) {
            $table->id();
            $table->string('kind')->default(LexiconKind::Word->value);
            $table->string('english_text');
            $table->string('ipa')->nullable();
            $table->string('part_of_speech', 16)->nullable();
            $table->string('arabic_meaning')->nullable();
            $table->text('simple_explanation')->nullable();
            $table->text('hotel_example')->nullable();
            $table->text('hotel_example_arabic')->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('source')->default('manual');
            $table->json('ai_draft')->nullable();
            $table->string('ai_status')->nullable();
            $table->boolean('show_meaning_enabled')->default(true);
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'department_id']);
            $table->index('hotel_id');
        });

        Schema::create('block_lexicon_item', function (Blueprint $table) {
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lexicon_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);

            $table->primary(['block_id', 'lexicon_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_lexicon_item');
        Schema::dropIfExists('lexicon_items');
    }
};
