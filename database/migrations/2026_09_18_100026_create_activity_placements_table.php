<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an activity appears: in a practice block or in a test (PRAC-05,
 * WRITE-05, TSTM-01, spec 0003 B.5).
 *
 * Polymorphic on the placeable (App\Models\Block or App\Models\Test) so the
 * same activity row can sit in a lesson and in a test with a per-placement
 * override such as a different prompt, without a copy that would drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('placeable_type');
            $table->unsignedBigInteger('placeable_id');
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('overrides')->nullable();
            $table->timestamps();

            $table->index(['placeable_type', 'placeable_id']);
            $table->index('activity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_placements');
    }
};
