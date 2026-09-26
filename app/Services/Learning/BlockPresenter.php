<?php

namespace App\Services\Learning;

use App\Enums\Accent;
use App\Enums\BlockType;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\AiScenario;
use App\Models\Attempt;
use App\Models\Block;
use App\Models\Lesson;
use App\Models\LexiconItem;
use App\Models\RoleplayAttempt;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Shapes one block for the step page (LESSON-01, LESSON-06, CTRL-01..03,
 * CTRL-05, PRAC-05, RP-01; spec 0003 B.10, Part E).
 *
 * The generic part is the same for every type: the settings with media ids
 * resolved and playable sentences paired with their stored clips. On top of
 * that, the types that own other rows get them shaped here as well: lexicon
 * items for vocabulary and expressions, activity cards for practice,
 * scenario cards for AI role-play, the lesson summary for the complete
 * screen. The keys are always present so a page can type one shape.
 *
 * Arabic is included for lesson pages: it is rendered only behind Show
 * Meaning (CTRL-02), and this presenter never serves a test page (CTRL-04).
 */
class BlockPresenter
{
    public function __construct(
        private readonly PayloadResolver $resolver,
        private readonly LessonNavigator $navigator,
        private readonly JourneyService $journey,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Block $block, User $user): array
    {
        $type = $block->type;
        // The step plays in its lesson's accent voice; a lesson with no
        // accent keeps the platform voice (spec 0006 §3).
        $lesson = $block->lesson;
        $accent = $lesson?->accent;

        return [
            'id' => $block->id,
            'type' => $type->value,
            'title' => $block->title,
            'heading' => $block->heading(),
            'stepLabel' => $type->stepLabel(),
            'layout' => $block->layout,
            'accent' => $lesson?->speakingAccent()->value,
            'settings' => $this->resolver->forAccent($accent)->resolve($block->settings ?? []),
            'lexicon' => $type->holdsLexicon() ? $this->lexicon($block, $user, $accent) : [],
            'activities' => $type->holdsActivities() ? $this->activities($block, $user) : [],
            'scenarios' => $type === BlockType::AiRoleplay ? $this->scenarios($block, $user) : [],
            'summary' => $type === BlockType::Complete ? $this->summary($block, $user) : null,
        ];
    }

    /**
     * The words or expressions of a vocabulary / expressions block, in order,
     * with their audio, their Show Meaning content and whether they are
     * already in the learner's phrasebook (PHRASE-02).
     *
     * @return list<array<string, mixed>>
     */
    public function lexicon(Block $block, User $user, ?Accent $accent = null): array
    {
        $items = $block->lexiconItems()->with('image')->get();

        if ($items->isEmpty()) {
            return [];
        }

        $texts = [];

        foreach ($items as $item) {
            foreach ($item->playableTexts() as $text) {
                $texts[] = $text;
            }
        }

        $audio = $this->resolver->forAccent($accent)->audioForMany($texts);

        /** @var list<int> $saved */
        $saved = $user->phrasebookItems()
            ->whereIn('lexicon_item_id', $items->pluck('id'))
            ->pluck('lexicon_item_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $featuredId = $block->setting('featured_lexicon_item_id');
        $featuredId = is_numeric($featuredId) ? (int) $featuredId : null;

        $silent = ['normal' => null, 'slow' => null];

        return array_values($items->map(fn (LexiconItem $item): array => [
            'id' => $item->id,
            'kind' => $item->kind->value,
            'text' => $item->english_text,
            'ipa' => $item->ipa,
            'partOfSpeech' => $item->part_of_speech,
            'image' => $this->resolver->media($item->image),
            'audio' => $audio[$item->english_text] ?? $silent,
            'example' => $item->hotel_example,
            'exampleAudio' => $item->hotel_example === null ? null : ($audio[$item->hotel_example] ?? $silent),
            'showMeaning' => $item->allowsShowMeaning(),
            'meaning' => $item->allowsShowMeaning() ? [
                'arabic' => $item->arabic_meaning,
                'explanation' => $item->simple_explanation,
                'exampleArabic' => $item->hotel_example_arabic,
            ] : null,
            'saved' => in_array($item->id, $saved, true),
            'featured' => $featuredId !== null && $item->id === $featuredId,
        ])->all());
    }

    /**
     * The practice hub cards (PRAC-01..03, PRAC-07).
     *
     * @return list<array<string, mixed>>
     */
    public function activities(Block $block, User $user): array
    {
        $lesson = $block->lesson()->firstOrFail();
        $placements = $block->placements()->with('activity')->get();

        if ($placements->isEmpty()) {
            return [];
        }

        $attempts = Attempt::query()
            ->where('user_id', $user->id)
            ->whereIn('placement_id', $placements->pluck('id'))
            ->whereNull('test_attempt_id')
            ->get()
            ->groupBy('placement_id');

        $cards = [];

        foreach ($placements as $placement) {
            $activity = $placement->activity;

            if ($activity === null || ! $activity->isPublished()) {
                continue;
            }

            /** @var Collection<int, Attempt> $own */
            $own = $attempts->get($placement->id, collect());
            $used = $own->count();
            $best = $own->whereNotNull('score')->sortByDesc(fn (Attempt $a): float => (float) $a->score)->first();

            $cards[] = [
                'id' => $placement->id,
                'activityId' => $activity->id,
                'type' => $activity->type->value,
                'label' => $activity->title ?? $activity->type->label(),
                'description' => $activity->type->hubDescription(),
                'tone' => $activity->type->tone(),
                'icon' => $activity->type->icon(),
                'preview' => $this->preview($activity),
                'itemCount' => $activity->itemCount(),
                'url' => route('learn.lessons.activity', ['lesson' => $lesson, 'block' => $block, 'placement' => $placement]),
                'attemptsAllowed' => $activity->attempts_allowed,
                'attemptsUsed' => $used,
                'attemptsLeft' => $activity->allowsUnlimitedAttempts() ? null : max(0, $activity->attempts_allowed - $used),
                'done' => $own->contains(fn (Attempt $a): bool => $a->is_correct === true || ! $activity->isAutoScored()),
                'bestScore' => $best === null ? null : [
                    'score' => (float) $best->score,
                    'maxScore' => $best->max_score === null ? null : (float) $best->max_score,
                ],
            ];
        }

        return $cards;
    }

    /**
     * A small preview of the first item for the hub card (photo_7): the
     * option or target images, or the sentence of a fill-in.
     *
     * @return array{images: list<string>, sentence: string|null}
     */
    private function preview(Activity $activity): array
    {
        $version = $activity->currentVersion()->first();
        $items = $version?->items() ?? [];
        $first = $items[0] ?? null;

        if (! is_array($first)) {
            return ['images' => [], 'sentence' => null];
        }

        $resolved = $this->resolver->resolve([$first])[0] ?? $first;
        $sentence = $resolved['sentence'] ?? null;

        return [
            'images' => array_slice($this->previewImages($resolved), 0, 4),
            'sentence' => is_string($sentence) ? $sentence : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function previewImages(array $item): array
    {
        $urls = [];

        foreach (['image', 'poster', 'thumbnail'] as $key) {
            $url = is_array($item[$key] ?? null) ? ($item[$key]['url'] ?? null) : null;

            if (is_string($url)) {
                $urls[] = $url;
            }
        }

        foreach (['options', 'targets', 'cards'] as $key) {
            $list = $item[$key] ?? null;

            if (is_array($list)) {
                foreach ($list as $entry) {
                    $url = is_array($entry) ? ($entry['image']['url'] ?? null) : null;

                    if (is_string($url)) {
                        $urls[] = $url;
                    }
                }
            }
        }

        return $urls;
    }

    /**
     * The AI role-play scenario cards of this block (RP-01, RP-05).
     *
     * Only scenarios the learner may practise are listed, in the order the
     * block names them.
     *
     * @return list<array<string, mixed>>
     */
    public function scenarios(Block $block, User $user): array
    {
        $ids = $block->scenarioIds();

        if ($ids === []) {
            return [];
        }

        $lesson = $block->lesson()->firstOrFail();

        $scenarios = AiScenario::query()
            ->forLearner($user)
            ->whereIn('id', $ids)
            ->with('thumbnail')
            ->get()
            ->sortBy(fn (AiScenario $scenario): int => (int) array_search($scenario->id, $ids, true))
            ->values();

        $used = RoleplayAttempt::query()
            ->where('user_id', $user->id)
            ->counted()
            ->whereIn('ai_scenario_id', $scenarios->pluck('id'))
            ->get()
            ->countBy('ai_scenario_id');

        return array_values($scenarios->map(function (AiScenario $scenario) use ($lesson, $block, $used): array {
            $attemptsUsed = (int) ($used->get($scenario->id) ?? 0);

            return [
                'id' => $scenario->id,
                'title' => $scenario->title,
                'description' => $scenario->description,
                'difficulty' => $scenario->difficulty->value,
                'icon' => $scenario->icon,
                'thumbnail' => $this->resolver->media($scenario->thumbnail),
                'url' => route('learn.roleplay.ready', ['lesson' => $lesson, 'block' => $block, 'scenario' => $scenario]),
                'attemptsAllowed' => $scenario->attempts_allowed,
                'attemptsUsed' => $attemptsUsed,
                'attemptsLeft' => max(0, $scenario->attempts_allowed - $attemptsUsed),
            ];
        })->all());
    }

    /**
     * What the Lesson Completed screen reports ("2 of 8 lessons") plus the
     * per-block summary rows (LESSON-05; photo_19).
     *
     * @return array{lessonsCompleted: int, lessonsTotal: int, nextLesson: array{id: int, title: string, url: string}|null, rows: list<array{type: string, title: string, subtitle: string, done: bool}>, homeUrl: string, lessonsUrl: string}
     */
    public function summary(Block $block, User $user): array
    {
        /** @var Lesson $lesson */
        $lesson = $block->lesson()->firstOrFail();
        $next = $this->navigator->nextLessonAfter($user, $lesson);

        return [
            'lessonsCompleted' => $this->journey->lessonsCompleted($user),
            'lessonsTotal' => $this->journey->lessonsTotal($user),
            'nextLesson' => $next === null ? null : [
                'id' => $next->id,
                'title' => $next->title,
                'url' => route('learn.lessons.show', ['lesson' => $next]),
            ],
            'rows' => $this->summaryRows($lesson, $user),
            'homeUrl' => route('learn.home'),
            'lessonsUrl' => route('learn.lessons'),
        ];
    }

    /**
     * One row per content block of the finished lesson, each already ticked
     * (reaching this step means the lesson is complete, LESSON-05).
     *
     * @return list<array{type: string, title: string, subtitle: string, done: bool}>
     */
    private function summaryRows(Lesson $lesson, User $user): array
    {
        $rows = [];

        foreach ($lesson->visibleBlocks()->get() as $block) {
            $row = $this->summaryRow($block, $lesson, $user);

            if ($row !== null) {
                $rows[] = $row + ['done' => true];
            }
        }

        return $rows;
    }

    /**
     * @return array{type: string, title: string, subtitle: string}|null
     */
    private function summaryRow(Block $block, Lesson $lesson, User $user): ?array
    {
        return match ($block->type) {
            BlockType::Situation => ['type' => 'situation', 'title' => __('Scenario'), 'subtitle' => $lesson->title],
            BlockType::Vocabulary => ['type' => 'vocabulary', 'title' => __('Key Vocabulary'), 'subtitle' => trans_choice(':count word|:count words', $block->lexiconItems()->count())],
            BlockType::Expressions => ['type' => 'expressions', 'title' => __('Useful Expressions'), 'subtitle' => trans_choice(':count expression|:count expressions', $block->lexiconItems()->count())],
            BlockType::ListenRepeat => ['type' => 'listen_repeat', 'title' => __('Listening'), 'subtitle' => __('Completed')],
            BlockType::Dialogue => ['type' => 'dialogue', 'title' => __('Dialogue Practice'), 'subtitle' => __('Completed')],
            BlockType::Video => ['type' => 'video', 'title' => __('Video'), 'subtitle' => __('Completed')],
            BlockType::Practice => ['type' => 'practice', 'title' => __('Practice'), 'subtitle' => __('Completed')],
            BlockType::AiRoleplay => ['type' => 'ai_roleplay', 'title' => __('AI Role-play'), 'subtitle' => $this->roleplaySubtitle($block, $user)],
            default => null,
        };
    }

    private function roleplaySubtitle(Block $block, User $user): string
    {
        $ids = $block->scenarioIds();

        if ($ids === []) {
            return __('Completed');
        }

        $allowed = (int) AiScenario::query()->whereIn('id', $ids)->max('attempts_allowed');
        $used = RoleplayAttempt::query()
            ->where('user_id', $user->id)
            ->counted()
            ->whereIn('ai_scenario_id', $ids)
            ->count();

        return __(':used of :allowed attempts completed', ['used' => min($used, max($allowed, 1)), 'allowed' => max($allowed, 1)]);
    }

    /**
     * The lesson header every step shares.
     *
     * @return array<string, mixed>
     */
    public function lesson(Lesson $lesson): array
    {
        $course = $lesson->course()->firstOrFail();
        $department = $course->department()->first();

        return [
            'id' => $lesson->id,
            'title' => $lesson->title,
            'introduction' => $lesson->introduction,
            'objectives' => $lesson->objectives ?? [],
            'cover' => $this->resolver->media($lesson->cover),
            'estimatedMinutes' => $lesson->estimated_minutes,
            'positionInCourse' => $lesson->stepNumberIn($course),
            'courseLessonCount' => $lesson->totalStepsIn($course),
            'course' => ['id' => $course->id, 'title' => $course->title, 'tone' => $course->tone],
            'department' => ['name' => $department->name ?? ''],
        ];
    }

    /**
     * Which placement an activity page belongs to, for the hub card and the
     * back link.
     */
    public function placementUrl(Lesson $lesson, Block $block, ActivityPlacement $placement): string
    {
        return route('learn.lessons.activity', ['lesson' => $lesson, 'block' => $block, 'placement' => $placement]);
    }
}
