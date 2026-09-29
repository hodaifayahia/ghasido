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
     * The builder's question kind for a stored activity type. The inverse of
     * activityType(); speaking and ordering only come from AI drafts.
     */
    public static function kindFor(ActivityType $type, ?string $skillLabel = null): string
    {
        // True / False is stored as a two-option multiple choice; its skill
        // label is what tells the builder it is a True / False question.
        if ($type === ActivityType::MultipleChoice && $skillLabel === 'True / False') {
            return 'true_false';
        }
        if ($type === ActivityType::MultipleChoice && $skillLabel === 'Image Question') {
            return 'image';
        }

        return match ($type) {
            ActivityType::WordsSentences => 'fill_blank',
            ActivityType::ListenMatch => 'matching',
            ActivityType::ShortAnswer => 'short_answer',
            ActivityType::Writing => 'writing',
            ActivityType::ListenChoose, ActivityType::BestResponse => 'audio',
            ActivityType::LookListen => 'image',
            ActivityType::WatchRespond => 'video',
            ActivityType::Speaking => 'speaking',
            ActivityType::DialogueOrder, ActivityType::PictureOrder => 'ordering',
            default => 'multiple_choice',
        };
    }

    /**
     * @param  array{kind: string, text: string, options: list<array{id: string, text: string, correct: bool}>}  $data
     */
    public function addQuestion(Test $test, array $data, User $actor): ActivityPlacement
    {
        return DB::transaction(function () use ($test, $data, $actor): ActivityPlacement {
            $type = $this->activityType($data['kind']);
            $options = $this->options($data['options'], $data['kind']);
            $activity = new Activity;
            $activity->fill([
                'type' => $type,
                'skill_label' => $this->skillLabel($data['kind']),
                'title' => $test->title.' – Question '.(((int) $test->questions()->max('position')) + 1),
                'prompt' => $data['text'],
                'payload' => ['items' => [$this->newItem($data, $options)]],
                'scoring' => null,
                'show_meaning_enabled' => false,
                'status' => ContentStatus::Draft,
                'created_by' => $actor->id,
                'department_id' => $test->department_id,
                'hotel_id' => $test->hotel_id,
            ])->save();

            $placement = $test->questions()->create([
                'activity_id' => $activity->id,
                'position' => ((int) $test->questions()->max('position')) + 1,
            ]);
            AuditLog::record($test, 'test.question.created', ['activity_id' => $activity->id]);

            return $placement;
        });
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
     * @param  array{kind: string, text: string, options: list<array{id: string, text: string, correct: bool}>}  $data
     */
    public function updateQuestion(ActivityPlacement $placement, array $data): ActivityPlacement
    {
        return DB::transaction(function () use ($placement, $data): ActivityPlacement {
            $activity = $placement->activity()->firstOrFail();
            $kindUnchanged = self::kindFor($activity->type, $activity->skill_label) === $data['kind'];
            $type = $kindUnchanged ? $activity->type : $this->activityType($data['kind']);
            $options = $this->options($data['options'], $data['kind']);
            $oldItem = $activity->items()[0] ?? ['id' => 'i1'];
            $item = $this->newItem($data, $options);

            // Retain attached question media when the editor only changes
            // text or answer structure. Media is independently replaceable.
            foreach (['image', 'audio', 'video', 'poster', 'media'] as $key) {
                if (! array_key_exists($key, $item) && array_key_exists($key, $oldItem)) {
                    $item[$key] = $oldItem[$key];
                }
            }

            $activity->fill([
                'type' => $type,
                'prompt' => $data['text'],
                'payload' => ['items' => [$item]],
            ]);

            // A new kind gets its own label (True / False is told apart from
            // multiple choice by it); an unchanged kind keeps an AI draft's.
            if (! $kindUnchanged) {
                $activity->skill_label = $this->skillLabel($data['kind']);
            }
            AuditLog::record($activity, 'test.question.updated');
            $activity->save();

            return $placement->refresh();
        });
    }

    /**
     * Attach media in the answerable item's payload. The Activity model
     * creates the immutable next version when the payload changes (DATA-11,
     * TEST-09).
     */
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

    public function removeQuestion(ActivityPlacement $placement, Test $test): void
    {
        DB::transaction(function () use ($placement, $test): void {
            $placement->delete();
            $test->questions()->where('position', '>', $placement->position)->decrement('position');
            AuditLog::record($test, 'test.question.removed', ['activity_id' => $placement->activity_id]);
        });
    }

    /**
     * One new item in the shape its type needs (spec 0003 B.9): a speaking
     * item has no options, an ordering item lists its lines as sentences in
     * the correct order given.
     *
     * @param  array{kind: string, text: string, options: list<array{id: string, text: string, correct: bool}>}  $data
     * @param  list<array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    private function newItem(array $data, array $options): array
    {
        $media = array_filter(
            is_array($data['media'] ?? null) ? $data['media'] : [],
            static fn (mixed $id): bool => is_numeric($id) && (int) $id > 0,
        );
        $item = ['id' => 'i1'];

        foreach ($media as $kind => $id) {
            $item[$kind] = (int) $id;
        }

        return match ($data['kind']) {
            'speaking' => [
                ...$item,
                'question' => $data['text'],
                'situation' => $data['text'],
                'instruction' => 'Record a short and polite response.',
                'max_seconds' => max(5, (int) ($data['speaking_seconds'] ?? 30)),
            ],
            'writing' => [
                ...$item,
                'scenario' => $data['text'],
                'request_text' => trim((string) ($data['request_text'] ?? '')) ?: $data['text'],
                'information' => array_values(array_filter($data['information'] ?? [], 'is_string')),
                'min_words' => 20,
            ],
            'short_answer' => [
                ...$item,
                'question' => $data['text'],
                'accepted_answers' => array_values(array_filter($data['accepted_answers'] ?? [], 'is_string')),
            ],
            'fill_blank' => [
                ...$item,
                'sentence' => $data['text'],
                'accepted_answers' => array_values(array_filter($data['accepted_answers'] ?? [], 'is_string')),
                'options' => [],
            ],
            'matching' => $this->matchingItem($data, $item),
            'ordering' => $this->orderingItem($data, $item),
            'audio' => [
                ...$item,
                'question' => $data['text'],
                'audio_text' => trim((string) ($data['audio_text'] ?? '')) ?: $data['text'],
                'options' => $options,
                'correct' => $this->correctOption($data['options']),
            ],
            'video' => [
                ...$item,
                'question' => $data['text'],
                'subtitle' => $data['text'],
                'options' => $options,
                'correct' => $this->correctOption($data['options']),
            ],
            'image', 'multiple_choice', 'true_false' => [
                ...$item,
                'question' => $data['text'],
                'options' => $options,
                'correct' => $this->correctOption($data['options']),
            ],
            default => [
                ...$item,
                'question' => $data['text'],
                'options' => $options,
                'correct' => $this->correctOption($data['options']),
            ],
        };
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $base */
    private function matchingItem(array $data, array $base): array
    {
        $prompts = [];
        $targets = [];
        $pairs = [];

        foreach (array_values($data['pairs'] ?? []) as $index => $pair) {
            if (! is_array($pair)) {
                continue;
            }

            $promptId = 'p'.($index + 1);
            $targetId = 't'.($index + 1);
            $prompts[] = ['id' => $promptId, 'audio_text' => (string) ($pair['left'] ?? '')];
            $targets[] = ['id' => $targetId, 'label' => (string) ($pair['right'] ?? ''), 'image' => null];
            $pairs[$promptId] = $targetId;
        }

        return [...$base, 'prompts' => $prompts, 'targets' => $targets, 'pairs' => $pairs];
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $base */
    private function orderingItem(array $data, array $base): array
    {
        $options = array_values($data['options'] ?? []);
        $sentences = [];
        $order = [];

        foreach ($options as $index => $option) {
            if (! is_array($option)) {
                continue;
            }

            $id = 's'.($index + 1);
            $sentences[] = ['id' => $id, 'text' => (string) ($option['text'] ?? '')];
            $order[] = $id;
        }

        // The learner sees a scrambled list; the saved `order` is the
        // author's correct sequence (TEST-05/06).
        return [...$base, 'sentences' => array_reverse($sentences), 'order' => $order];
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

    private function activityType(string $kind): ActivityType
    {
        return match ($kind) {
            'fill_blank' => ActivityType::WordsSentences,
            'matching' => ActivityType::ListenMatch,
            'short_answer' => ActivityType::ShortAnswer,
            'audio' => ActivityType::ListenChoose,
            'image' => ActivityType::MultipleChoice,
            'video' => ActivityType::WatchRespond,
            'speaking' => ActivityType::Speaking,
            'ordering' => ActivityType::DialogueOrder,
            'writing' => ActivityType::Writing,
            default => ActivityType::MultipleChoice,
        };
    }

    private function skillLabel(string $kind): string
    {
        return match ($kind) {
            'true_false' => 'True / False',
            'fill_blank' => 'Vocabulary',
            'short_answer' => 'Short Answer',
            'audio' => 'Listening',
            'image' => 'Image Question',
            'video' => 'Video',
            'speaking' => 'Speaking',
            'ordering' => 'Ordering',
            'writing' => 'Writing',
            'matching' => 'Matching',
            default => 'Multiple Choice',
        };
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
            static function (array $option): array {
                $normalized = ['id' => $option['id'], 'text' => $option['text']];

                if (is_numeric($option['image_id'] ?? null)) {
                    $normalized['image'] = (int) $option['image_id'];
                }

                if (is_numeric($option['audio_id'] ?? null)) {
                    $normalized['audio'] = (int) $option['audio_id'];
                }

                if (is_string($option['audio_text'] ?? null) && trim($option['audio_text']) !== '') {
                    $normalized['audio_text'] = trim($option['audio_text']);
                }

                return $normalized;
            },
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
