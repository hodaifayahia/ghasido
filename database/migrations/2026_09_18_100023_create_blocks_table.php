<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One block is one CMS unit and one employee step, the same row seen from
 * both sides (LESSON-01, LESSON-02, BLD-03, BLD-04, BLD-07, spec 0003 B.5).
 *
 * The admin chooses which blocks a lesson has and in what order, so nothing
 * about the pipeline is hard coded: the renderer is a `type -> component` map
 * over these rows. Simple blocks keep their content in `settings` (contracts
 * in spec 0003 B.10); vocabulary and expressions blocks point at shared
 * lexicon items through block_lexicon_item; practice blocks own activities
 * through activity_placements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('layout')->default('full');
            $table->json('settings')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['lesson_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
