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
    public static function kindFor(ActivityType $type): string
    {
        return match ($type) {
            ActivityType::WordsSentences => 'fill_blank',
            ActivityType::ListenMatch => 'matching',
            ActivityType::Writing => 'short_answer',
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
            // An unchanged kind keeps the stored type, so saving an AI-drafted
            // listening (best_response) question never turns it into another
            // type (DATA-11: the edit is a new version of the same question).
            $kindUnchanged = self::kindFor($activity->type) === $data['kind'];
            $type = $kindUnchanged ? $activity->type : $this->activityType($data['kind']);
            $item = $activity->items()[0] ?? ['id' => 'i1'];
            $item['question'] = $data['text'];

            if ($kindUnchanged && in_array($type, [ActivityType::Speaking, ActivityType::DialogueOrder, ActivityType::PictureOrder, ActivityType::Writing], true)) {
                // No options to edit on these: only the prompt changes.
                $activity->fill([
                    'prompt' => $data['text'],
                    'payload' => ['items' => [$item]],
                ]);
                AuditLog::record($activity, 'test.question.updated');
                $activity->save();

                return $placement->refresh();
            }

            $options = $this->options($data['options'], $data['kind']);
            $item['options'] = $options;
            $item['correct'] = $this->correctOption($data['options']);

            $activity->fill([
                'type' => $type,
                'prompt' => $data['text'],
                'payload' => ['items' => [$item]],
                'show_meaning_enabled' => false,
            ]);
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
                if ($kind === 'image' || $kind === 'video') {
                    unset($item[$kind]);
                }
            } else {
                $mediaMap[$kind] = $mediaId;
                if ($kind === 'image' || $kind === 'video') {
                    $item[$kind] = $mediaId;
                }
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
        return match ($data['kind']) {
            'speaking' => ['id' => 'i1', 'question' => $data['text'], 'instruction' => 'Record a short and polite response.', 'max_seconds' => 30],
            'ordering' => [
                'id' => 'i1',
                'question' => $data['text'],
                'sentences' => array_map(
                    static fn (array $option, int $index): array => ['id' => 's'.($index + 1), 'text' => $option['text']],
                    $data['options'],
                    array_keys($data['options']),
                ),
                'order' => array_map(static fn (int $index): string => 's'.($index + 1), array_keys($data['options'])),
            ],
            default => [
                'id' => 'i1',
                'question' => $data['text'],
                'options' => $options,
                'correct' => $this->correctOption($data['options']),
            ],
        };
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
            'short_answer' => ActivityType::Writing,
            'audio' => ActivityType::ListenChoose,
            'image' => ActivityType::LookListen,
            'video' => ActivityType::WatchRespond,
            'speaking' => ActivityType::Speaking,
            'ordering' => ActivityType::DialogueOrder,
            default => ActivityType::MultipleChoice,
        };
    }

    private function skillLabel(string $kind): string
    {
        return match ($kind) {
            'true_false' => 'True / False',
            'fill_blank' => 'Vocabulary',
            'short_answer' => 'Writing',
            'audio' => 'Listening',
            'image' => 'Visual',
            'video' => 'Video',
            'speaking' => 'Speaking',
            'ordering' => 'Ordering',
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
