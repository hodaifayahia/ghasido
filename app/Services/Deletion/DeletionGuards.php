<?php

namespace App\Services\Deletion;

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\AiScenario;
use App\Models\AutomationRule;
use App\Models\Block;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What blocks a safe delete, per kind of row (owner decision 2026-09-27).
 *
 * Every count uses the query builder, never Eloquent scopes, so a hotel or
 * learner scope can never hide a dependant. The labels are `singular` or
 * `singular|plural`; SafeDelete turns them into one sentence.
 *
 * Learner and research data always blocks (DATA-10): completions, answers,
 * test and role-play attempts, recordings, certificates, pronunciation
 * checks, phrasebook items and sent reminders (REM-06). Authorship columns
 * (created_by, actor_id…) never block: they are nulled and the audit row
 * keeps a snapshot of what was deleted.
 */
final class DeletionGuards
{
    /**
     * A learner's training records. `$learner` adds their AI usage rows,
     * which are research data for a learner and cost history for an admin.
     *
     * @return array<string, int>
     */
    public static function trainingRecords(User $user, bool $learner = true): array
    {
        $id = $user->id;

        return array_filter([
            'completed lesson step' => self::count('block_completions', 'user_id', $id),
            'completed lesson' => self::count('lesson_completions', 'user_id', $id),
            'test attempt' => self::count('test_attempts', 'user_id', $id),
            'answer' => self::count('attempts', 'user_id', $id),
            'role-play attempt' => DB::table('roleplay_attempts')->where('user_id', $id)->where('is_preview', false)->count(),
            'voice recording' => self::count('voice_recordings', 'user_id', $id),
            'pronunciation check' => self::count('pronunciation_attempts', 'user_id', $id),
            'certificate' => self::count('certificates', 'user_id', $id),
            'phrasebook item' => self::count('phrasebook_items', 'user_id', $id),
            'sent reminder' => self::count('reminders', 'user_id', $id),
            'AI usage record' => $learner ? self::count('ai_usages', 'user_id', $id) : 0,
        ]);
    }

    /**
     * @return array<string, int>
     */
    public static function hotel(Hotel $hotel): array
    {
        $id = $hotel->id;

        return array_filter([
            'account' => self::count('users', 'hotel_id', $id),
            'course' => self::count('courses', 'hotel_id', $id),
            'lesson' => self::count('lessons', 'hotel_id', $id),
            'test' => self::count('tests', 'hotel_id', $id),
            'AI scenario' => self::count('ai_scenarios', 'hotel_id', $id),
            'practice activity|practice activities' => self::count('activities', 'hotel_id', $id),
            'vocabulary item' => self::count('lexicon_items', 'hotel_id', $id),
            'media file' => self::count('media_assets', 'hotel_id', $id),
            'AI usage record' => self::count('ai_usages', 'hotel_id', $id),
            'pronunciation check' => self::count('pronunciation_attempts', 'hotel_id', $id),
            'AI point payment' => self::count('hotel_ai_point_top_ups', 'hotel_id', $id),
        ]);
    }

    /**
     * @return array<string, int>
     */
    public static function department(Department $department): array
    {
        $id = $department->id;

        $rules = AutomationRule::query()->get()->filter(
            fn (AutomationRule $rule): bool => in_array($id, array_map('intval', (array) ($rule->audience['department_ids'] ?? [])), true),
        )->count();

        return array_filter([
            'account' => self::count('users', 'department_id', $id),
            'individual subscriber' => self::count('individual_subscription_department', 'department_id', $id),
            'hotel' => self::count('seat_quotas', 'department_id', $id),
            'course' => self::count('courses', 'department_id', $id),
            'test' => self::count('tests', 'department_id', $id),
            'AI scenario' => self::count('ai_scenarios', 'department_id', $id),
            'vocabulary item' => self::count('lexicon_items', 'department_id', $id),
            'pronunciation check' => self::count('pronunciation_attempts', 'department_id', $id),
            'reminder rule' => $rules,
        ]);
    }

    /**
     * Learners with any progress in the lesson.
     *
     * @return array<string, int>
     */
    public static function lesson(Lesson $lesson): array
    {
        $blockIds = $lesson->blocks()->pluck('id')->all();

        $learners = collect()
            ->merge(DB::table('block_completions')->where('lesson_id', $lesson->id)->pluck('user_id'))
            ->merge(DB::table('lesson_completions')->where('lesson_id', $lesson->id)->pluck('user_id'))
            ->merge(DB::table('attempts')->where('lesson_id', $lesson->id)->pluck('user_id'))
            ->merge(DB::table('roleplay_attempts')->where('lesson_id', $lesson->id)->where('is_preview', false)->pluck('user_id'))
            ->merge(DB::table('pronunciation_attempts')->where('lesson_id', $lesson->id)->pluck('user_id'))
            ->merge($blockIds === [] ? [] : DB::table('voice_recordings')
                ->where('recordable_type', Block::class)
                ->whereIn('recordable_id', $blockIds)
                ->pluck('user_id'))
            ->unique()
            ->count();

        return array_filter(['learner with progress|learners with progress' => $learners]);
    }

    /**
     * Learners who practised it, and published lessons that offer it.
     *
     * @return array<string, int>
     */
    public static function scenario(AiScenario $scenario): array
    {
        $learners = DB::table('roleplay_attempts')
            ->where('ai_scenario_id', $scenario->id)
            ->where('is_preview', false)
            ->distinct()
            ->count('user_id');

        $published = self::roleplayBlocksOffering($scenario)
            ->pluck('lesson_id')
            ->unique()
            ->filter(fn (mixed $lessonId): bool => Lesson::query()->whereKey($lessonId)->where('status', ContentStatus::Published->value)->exists())
            ->count();

        return array_filter([
            'learner who practised it|learners who practised it' => $learners,
            'published lesson' => $published,
        ]);
    }

    /**
     * The role-play steps that offer a scenario, through the pivot or the
     * older `settings.scenario_ids` list (Block::scenarioIds reads both).
     *
     * @return Collection<int, Block>
     */
    public static function roleplayBlocksOffering(AiScenario $scenario): Collection
    {
        return Block::query()
            ->where('type', BlockType::AiRoleplay->value)
            ->get()
            ->filter(fn (Block $block): bool => in_array($scenario->id, $block->scenarioIds(), true)
                || in_array($scenario->id, array_map('intval', (array) $block->setting('scenario_ids', [])), true))
            ->values();
    }

    /**
     * @return array<string, int>
     */
    public static function test(Test $test): array
    {
        $learners = DB::table('test_attempts')->where('test_id', $test->id)->distinct()->count('user_id');

        return array_filter(['learner who took it|learners who took it' => $learners]);
    }

    private static function count(string $table, string $column, int $id): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->where($column, $id)->count();
    }
}
