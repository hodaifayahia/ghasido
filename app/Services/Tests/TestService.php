<?php

namespace App\Services\Tests;

use App\Enums\ActivityType;
use App\Enums\ContentStatus;
use App\Enums\ResultsVisibility;
use App\Enums\TestType;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Writes the assessment builder's aggregate (TEST-01..10, TSTM-01..05).
 * Activities remain versioned rows so existing answers can never be rewritten.
 */
class TestService
{
    /**
     * @param  array{title: string, type: TestType, department_id: int, hotel_id: int|null, time_limit_seconds: int|null}  $data
     */
    public function create(array $data, User $actor): Test
    {
        return DB::transaction(function () use ($data): Test {
            $test = new Test;
            $test->fill([
                'title' => $data['title'],
                'type' => $data['type'],
                'department_id' => $data['department_id'],
                'hotel_id' => $data['hotel_id'],
                'intro' => ['description' => ''],
                'settings' => $this->defaultSettings($data['time_limit_seconds']),
                'status' => ContentStatus::Draft,
            ]);
            $test->save();
            AuditLog::record($test, 'test.created', ['created' => $test->only(['title', 'type', 'department_id', 'hotel_id'])]);

            return $test;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Test $test, array $data, User $actor): Test
    {
        return DB::transaction(function () use ($test, $data): Test {
            $test->fill($data);
            AuditLog::record($test, 'test.updated');
            $test->save();

            return $test;
        });
    }

    public function publish(Test $test, User $actor): Test
    {
        return DB::transaction(function () use ($test): Test {
            $test->status = ContentStatus::Published;
            AuditLog::record($test, 'test.published');
            $test->save();

            // Publishing is the admin's explicit review of every AI draft on
            // the test (GEN-03): release them together.
            foreach ($test->questions()->with('activity')->get() as $placement) {
                $this->release($placement);
            }

            return $test;
        });
    }

    /**
     * Release one AI-drafted question to learners (GEN-03). A placement that
     * is not a draft is left alone.
     */
    public function release(ActivityPlacement $placement): ActivityPlacement
    {
        if (Test::isAiDraft($placement)) {
            $overrides = $placement->overrides ?? [];
            unset($overrides[Test::AI_DRAFT_OVERRIDE]);
            $placement->overrides = $overrides === [] ? null : $overrides;
            $placement->save();

            $activity = $placement->activity;

            if ($activity !== null && $activity->status !== ContentStatus::Published) {
                // Status is not content: no new version is written (DATA-11).
                $activity->status = ContentStatus::Published;
                $activity->save();
            }

            AuditLog::record($placement, 'test.question.released', ['activity_id' => $placement->activity_id]);
        }

        return $placement;
    }

    /**
     * The builder's question kind for a stored activity type: the type
     * itself for the client's ten types (client report 2026-09-29), the
     * nearest of the ten for an older type.
     */
    public static function kindFor(ActivityType $type): string
    {
        if (! $type->isLegacy()) {
            return $type->value;
        }

        return match ($type) {
            ActivityType::WordsSentences => ActivityType::FillBlank->value,
            ActivityType::ListenMatch => ActivityType::Matching->value,
            ActivityType::ListenChoose, ActivityType::BestResponse => ActivityType::AudioQuestion->value,
            ActivityType::LookListen => ActivityType::ImageQuestion->value,
            ActivityType::WatchRespond => ActivityType::VideoQuestion->value,
            ActivityType::DialogueOrder, ActivityType::PictureOrder => ActivityType::Ordering->value,
            default => ActivityType::MultipleChoice->value,
        };
    }

    /**
     * Add one question built in the shared activity editor: its type, its
     * instruction and its one item (spec 0003 B.9). It starts as a draft,
     * like every question until the test is published.
     *
     * @param  array{type: ActivityType, prompt: string, title?: string|null, payload: array<string, mixed>}  $data
     */
    public function addActivity(Test $test, array $data, User $actor): ActivityPlacement
    {
        return DB::transaction(function () use ($test, $data, $actor): ActivityPlacement {
            $number = ((int) $test->questions()->max('position')) + 1;
            $title = trim((string) ($data['title'] ?? ''));
            $activity = new Activity;
            $activity->fill([
                'type' => $data['type'],
                'skill_label' => $data['type']->defaultSkillLabel(),
                'title' => $title !== '' ? $title : $test->title.' – Question '.$number,
                'prompt' => $data['prompt'],
                'payload' => $data['payload'],
                'scoring' => null,
                'show_meaning_enabled' => false,
                'status' => ContentStatus::Draft,
                'created_by' => $actor->id,
                'department_id' => $test->department_id,
                'hotel_id' => $test->hotel_id,
            ])->save();

            $placement = $test->questions()->create([
                'activity_id' => $activity->id,
                'position' => $number,
            ]);
            AuditLog::record($test, 'test.question.created', ['activity_id' => $activity->id, 'type' => $activity->type->value]);

            return $placement;
        });
    }

    /**
     * Save an edited question. The type never changes (a different kind of
     * question is a new one); a changed payload is a new version, so the
     * answers already given keep the version they answered (DATA-11).
     *
     * @param  array{prompt: string, title?: string|null, payload: array<string, mixed>}  $data
     */
    public function updateActivity(ActivityPlacement $placement, array $data): ActivityPlacement
    {
        return DB::transaction(function () use ($placement, $data): ActivityPlacement {
            $activity = $placement->activity()->firstOrFail();
            $title = trim((string) ($data['title'] ?? ''));
            $activity->fill([
                'prompt' => $data['prompt'],
                'payload' => $data['payload'],
                'show_meaning_enabled' => false,
            ]);

            if ($title !== '') {
                $activity->title = $title;
            }

            AuditLog::record($activity, 'test.question.updated', ['version_before' => $activity->current_version]);
            $activity->save();

            return $placement->refresh();
        });
    }

    /**
     * Add a question from the short form the CSV import (and the older
     * builder) sends: a kind, the question text and its options. It is
     * turned into the same type and item the shared editor writes.
     *
     * @param  array{kind: string, text: string, options: list<array{id: string, text: string, correct: bool}>}  $data
     */
    public function addQuestion(Test $test, array $data, User $actor): ActivityPlacement
    {
        [$type, $item] = $this->legacyItem($data);

        return $this->addActivity($test, [
            'type' => $type,
            'prompt' => $data['text'],
            'payload' => ['items' => [$item]],
        ], $actor);
    }

    /**
     * Add several questions at once, all or none (client request 2026-09-26).
     *
     * @param  list<array{kind: string, text: string, options: list<array{id: string, text: string, correct: bool}>}>  $questions
     */
    public function importQuestions(Test $test, array $questions, User $actor): int
    {
        return DB::transaction(function () use ($test, $questions, $actor): int {
            foreach ($questions as $question) {
                $this->addQuestion($test, $question, $actor);
            }

            AuditLog::record($test, 'test.questions.imported', ['count' => count($questions)]);

            return count($questions);
        });
    }

    /**
     * Save a question sent in the short form (kind, text, options). The
     * stored type is kept when the kind still names it; the rest of the
     * item (media, scripts) is kept and only the text and the answers
     * change (DATA-11: the edit is a new version of the same question).
     *
     * @param  array{kind: string, text: string, options: list<array{id: string, text: string, correct: bool}>}  $data
     */
    public function updateQuestion(ActivityPlacement $placement, array $data): ActivityPlacement
    {
        $activity = $placement->activity()->firstOrFail();
        [$type, $fresh] = $this->legacyItem($data);
        $item = $activity->items()[0] ?? ['id' => 'i1'];

        if ($type !== $activity->type && self::kindFor($activity->type) !== $data['kind']) {
            // A different kind of question: a new activity in its place, the
            // old one kept with its answers (DATA-10, DATA-11).
            return DB::transaction(function () use ($placement, $activity, $type, $fresh, $data): ActivityPlacement {
                $replacement = $activity->replicate(['current_version']);
                $replacement->fill(['type' => $type, 'prompt' => $data['text'], 'payload' => ['items' => [$fresh]], 'skill_label' => $type->defaultSkillLabel()]);
                $replacement->save();
                $placement->activity_id = $replacement->id;
                $placement->save();
                AuditLog::record($replacement, 'test.question.updated', ['replaced_activity_id' => $activity->id]);

                return $placement->refresh();
            });
        }

        $keep = in_array($activity->type, [ActivityType::Speaking, ActivityType::DialogueOrder, ActivityType::PictureOrder, ActivityType::Writing], true)
            && $data['options'] === [];
        $item = $keep ? [...$item, 'question' => $data['text']] : [...$item, ...$fresh, 'id' => $item['id'] ?? 'i1'];

        return $this->updateActivity($placement, [
            'prompt' => $data['text'],
            'payload' => ['items' => [$item]],
        ]);
    }

    /**
     * Attach media in the answerable item's payload. The Activity model
     * creates the immutable next version when the payload changes (DATA-11,
     * TEST-09).
     */
    /**
     * A new question order: every placement of the test exactly once
     * (client request 2026-09-29). Answers keep their placement, so the
     * order learners see changes without touching any stored answer.
     *
     * @param  list<int>  $placementIds
     */
    public function reorderQuestions(Test $test, array $placementIds): void
    {
        DB::transaction(function () use ($test, $placementIds): void {
            $ids = $test->questions()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

            if (count($placementIds) !== count($ids) || array_diff($placementIds, $ids) !== []) {
                throw new InvalidArgumentException(__('The order must list every question of this test exactly once.'));
            }

            foreach ($placementIds as $index => $id) {
                ActivityPlacement::query()->whereKey($id)->update(['position' => $index + 1]);
            }

            AuditLog::record($test, 'test.questions.reordered', ['order' => $placementIds]);
        });
    }

    public function attachMedia(ActivityPlacement $placement, string $kind, ?int $mediaId, User $actor): ActivityPlacement
    {
        return DB::transaction(function () use ($placement, $kind, $mediaId, $actor): ActivityPlacement {
            $activity = $placement->activity()->firstOrFail();

            if ($mediaId !== null) {
                $media = MediaAsset::query()->findOrFail($mediaId);
                abort_unless($media->kind->value === $kind, 422, __('The selected media type does not match the question slot.'));
            }

            $payload = $activity->payload;
            $items = $payload['items'] ?? [];
            $item = is_array($items[0] ?? null) ? $items[0] : ['id' => 'i1'];
            $mediaMap = is_array($item['media'] ?? null) ? $item['media'] : [];

            if ($mediaId === null) {
                unset($mediaMap[$kind]);
                unset($item[$kind]);
            } else {
                $mediaMap[$kind] = $mediaId;
                // Where the shared question editor reads it, audio included.
                $item[$kind] = $mediaId;
            }

            if ($mediaMap === []) {
                unset($item['media']);
            } else {
                $item['media'] = $mediaMap;
            }

            $payload['items'] = [$item, ...array_slice(is_array($items) ? $items : [], 1)];
            $activity->payload = $payload;
            $activity->save();
            AuditLog::record($activity, 'test.question.media.updated', [
                'kind' => $kind,
                'media_id' => $mediaId,
                'actor_id' => $actor->id,
            ]);

            return $placement->refresh();
        });
    }

    /**
     * Delete a test nobody has sat (the caller checks for sittings first,
     * DATA-10). Its placements go with it; the question activities and their
     * versions stay, because a lesson may place the same activity and
     * versions are never destroyed (PRAC-05, DATA-11). A Post-test paired to
     * it is unpaired, not deleted.
     */
    public function delete(Test $test): void
    {
        DB::transaction(function () use ($test): void {
            AuditLog::record($test, 'test.deleted', ['deleted' => [
                'title' => $test->title,
                'type' => $test->type->value,
                'department_id' => $test->department_id,
                'hotel_id' => $test->hotel_id,
                'questions' => $test->questions()->count(),
            ]]);

            ActivityPlacement::query()
                ->where('placeable_type', $test->getMorphClass())
                ->where('placeable_id', $test->id)
                ->delete();
            Test::query()->where('paired_test_id', $test->id)->update(['paired_test_id' => null]);
            $test->delete();
        });
    }

    public function removeQuestion(ActivityPlacement $placement, Test $test): void
    {
        DB::transaction(function () use ($placement, $test): void {
            $placement->delete();
            $test->questions()->where('position', '>', $placement->position)->decrement('position');
            AuditLog::record($test, 'test.question.removed', ['activity_id' => $placement->activity_id]);
        });
    }

    /**
     * The type and the one item a short-form question becomes (spec 0003
     * B.9): options with the correct one marked, a typed blank from the
     * `___` in the sentence, accepted answers, lines to order.
     *
     * @param  array{kind: string, text: string, options: list<array{id: string, text: string, correct: bool}>}  $data
     * @return array{0: ActivityType, 1: array<string, mixed>}
     */
    private function legacyItem(array $data): array
    {
        $text = $data['text'];
        $options = $data['options'];
        $correct = $this->correctOption($options);

        return match ($data['kind']) {
            'speaking' => [ActivityType::Speaking, ['id' => 'i1', 'question' => $text, 'instruction' => 'Record a short and polite response.', 'max_seconds' => 30]],
            'writing' => [ActivityType::Writing, ['id' => 'i1', 'question' => $text, 'scenario' => $text, 'min_words' => 10]],
            'short_answer' => $options === []
                ? [ActivityType::Writing, ['id' => 'i1', 'question' => $text, 'scenario' => $text, 'min_words' => 10]]
                : [ActivityType::ShortAnswer, ['id' => 'i1', 'question' => $text, 'accepted' => array_map(static fn (array $option): string => $option['text'], $options)]],
            'ordering' => [ActivityType::Ordering, self::orderingItem($text, $options)],
            'fill_blank' => [ActivityType::FillBlank, self::blankItem($text, $options, $correct)],
            'matching' => [ActivityType::Matching, self::matchingItem($text, $options)],
            'audio', 'audio_question' => [ActivityType::AudioQuestion, $this->optionItem($text, $options, $data['kind'])],
            'image', 'image_question' => [ActivityType::ImageQuestion, $this->optionItem($text, $options, $data['kind'])],
            'video', 'video_question' => [ActivityType::VideoQuestion, $this->optionItem($text, $options, $data['kind'])],
            default => [ActivityType::MultipleChoice, $this->optionItem($text, $options, $data['kind'])],
        };
    }

    /**
     * @param  list<array{id: string, text: string, correct: bool}>  $options
     * @return array<string, mixed>
     */
    private function optionItem(string $text, array $options, string $kind): array
    {
        return [
            'id' => 'i1',
            'question' => $text,
            'option_style' => 'text',
            'options' => $this->options($options, $kind),
            'correct' => $kind === 'true_false' && $options === [] ? 'A' : $this->correctOption($options),
        ];
    }

    /**
     * Lines given in the correct order; the learner sees them mixed up
     * (odd positions first), never in the answer order.
     *
     * @param  list<array{id: string, text: string, correct: bool}>  $options
     * @return array<string, mixed>
     */
    private static function orderingItem(string $text, array $options): array
    {
        $lines = array_map(
            static fn (array $option, int $index): array => ['id' => 's'.($index + 1), 'text' => $option['text']],
            $options,
            array_keys($options),
        );

        return [
            'id' => 'i1',
            'question' => $text,
            'sentences' => [
                ...array_values(array_filter($lines, static fn (int $index): bool => $index % 2 === 1, ARRAY_FILTER_USE_KEY)),
                ...array_values(array_filter($lines, static fn (int $index): bool => $index % 2 === 0, ARRAY_FILTER_USE_KEY)),
            ],
            'order' => array_map(static fn (array $line): string => $line['id'], $lines),
        ];
    }

    /**
     * `___` in the sentence becomes the blank; the correct option is its
     * accepted word (the other options were only distractors).
     *
     * @param  list<array{id: string, text: string, correct: bool}>  $options
     * @return array<string, mixed>
     */
    private static function blankItem(string $text, array $options, ?string $correct): array
    {
        $word = '';

        foreach ($options as $option) {
            if ($option['id'] === $correct) {
                $word = $option['text'];
            }
        }

        $sentence = (string) preg_replace('/_{2,}/', '[[b1]]', $text, 1);

        if (! str_contains($sentence, '[[b1]]')) {
            $sentence = rtrim($sentence).' [[b1]]';
        }

        return [
            'id' => 'i1',
            'question' => 'Type the missing word.',
            'sentence' => $sentence,
            'blanks' => [['id' => 'b1', 'accepted' => $word === '' ? [] : [$word]]],
        ];
    }

    /**
     * Options written `word = match` become the pairs.
     *
     * @param  list<array{id: string, text: string, correct: bool}>  $options
     * @return array<string, mixed>
     */
    private static function matchingItem(string $text, array $options): array
    {
        $prompts = [];
        $targets = [];
        $pairs = [];

        foreach ($options as $index => $option) {
            $parts = array_map('trim', explode('=', $option['text'], 2));
            $prompts[] = ['id' => (string) ($index + 1), 'text' => $parts[0]];
            $targets[] = ['id' => chr(97 + $index), 'text' => $parts[1] ?? ''];
            $pairs[(string) ($index + 1)] = chr(97 + $index);
        }

        return ['id' => 'i1', 'question' => $text, 'prompts' => $prompts, 'targets' => array_reverse($targets), 'pairs' => $pairs];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSettings(?int $timeLimitSeconds): array
    {
        return [
            'time_limit_seconds' => $timeLimitSeconds,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'single_attempt' => false,
            'results_visibility' => ResultsVisibility::Hidden->value,
            'show_answers' => false,
            'motivational_message' => false,
            'show_meaning' => true,
            'pass_score' => null,
            'on_timeout' => 'submit',
        ];
    }

    /**
     * @param  list<array{id: string, text: string, correct: bool}>  $options
     * @return list<array<string, mixed>>
     */
    private function options(array $options, string $kind): array
    {
        if ($kind === 'true_false' && $options === []) {
            return [
                ['id' => 'A', 'text' => 'True'],
                ['id' => 'B', 'text' => 'False'],
            ];
        }

        return array_map(
            static fn (array $option): array => ['id' => $option['id'], 'text' => $option['text']],
            $options,
        );
    }

    /**
     * @param  list<array{id: string, text: string, correct: bool}>  $options
     */
    private function correctOption(array $options): ?string
    {
        foreach ($options as $option) {
            if ($option['correct']) {
                return $option['id'];
            }
        }

        return null;
    }
}
