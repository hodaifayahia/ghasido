<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The level an AI score was judged against (spec 0005 §5.2; AIE-04, TEST-02,
 * TSTM-03).
 *
 * Practice and role-play are graded at the learner's measured level; Pre- and
 * Post-test answers on one fixed scale so the two sittings compare. The bar
 * that was used is written next to the score, so a research export can tell
 * them apart. Null = the fixed scale (or a learner with no level yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table): void {
            $table->string('graded_level', 20)->nullable();
        });

        Schema::table('roleplay_attempts', function (Blueprint $table): void {
            $table->string('graded_level', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('roleplay_attempts', function (Blueprint $table): void {
            $table->dropColumn('graded_level');
        });

        Schema::table('attempts', function (Blueprint $table): void {
            $table->dropColumn('graded_level');
        });
    }
};
