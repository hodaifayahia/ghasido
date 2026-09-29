<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Until 2026-09-29 the builder's "Quiz / Test" tile added a Practice block
 * titled "Quiz / Test", so it opened the Practice settings instead of a quiz
 * editor (client report). Those blocks become the new `quiz` block type.
 * Only the type and the placeholder title change: placements, activities and
 * every learner answer stay exactly as they are (DATA-10, DATA-11).
 */
return new class extends Migration
{
    /** The tile label in English and Arabic, as the old palette stored it. */
    private const array TITLES = ['Quiz / Test', 'اختبار قصير / اختبار'];

    public function up(): void
    {
        DB::table('blocks')
            ->where('type', 'practice')
            ->whereIn('title', self::TITLES)
            ->update(['type' => 'quiz', 'title' => null]);
    }

    public function down(): void
    {
        DB::table('blocks')
            ->where('type', 'quiz')
            ->update(['type' => 'practice', 'title' => 'Quiz / Test']);
    }
};
