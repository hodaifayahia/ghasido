<?php

namespace App\Services\Learning;

use App\Enums\Accent;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\ActivityVersion;
use App\Models\Attempt;
use App\Models\User;

/**
 * Shapes one placed activity for a practice page or a test question
 * (PRAC-01..07, TEST-03, TEST-05, CTRL-04, WRITE-05; spec 0003 B.9).
 *
 * The items come from the CURRENT version's payload with media ids resolved
 * and every playable sentence paired with its clips. In test mode the
 * answers (`correct`, `order`, `pairs`) and every Arabic field are stripped
 * before the payload leaves the server: on a test page, "disabled" is
 * otherwise only a UI state (CTRL-04, TEST-03).
 */
class ActivityPresenter
{
    public const MODE_PRACTICE = 'practice';

    public const MODE_TEST = 'test';

    /** @var list<string> */
    private const array ANSWER_KEYS = ['correct', 'order', 'pairs', 'accepted_answers', 'model_answer'];

    /**
     * A listening question's script, stripped from a test page so only the
     * stored recording can be played — never read, never spoken by browser
     * speech (CTRL-05, TEST-03). Its resolved `*_audio` sibling stays.
     *
     * @var list<string>
     */
    private const array LISTENING_SCRIPT_KEYS = ['audio_text', 'guest_audio_text'];

    public function __construct(private readonly PayloadResolver $resolver) {}

    /**
     * @return array<string, mixed>
     */
    public function present(ActivityPlacement $placement, User $user, string $mode = self::MODE_PRACTICE, ?Accent $accent = null): array
    {
        $activity = $placement->activity()->firstOrFail();
        $version = $this->currentVersion($activity);
        $isTest = $mode === self::MODE_TEST;

        // Lesson practice plays in the lesson's accent (spec 0006 §3); a
        // test keeps the platform voice.
        $resolvedPayload = $this->resolver->forAccent($accent)->resolve($version->payload);
        $resolvedItems = $resolvedPayload['items'] ?? [];
        $items = is_array($resolvedItems)
            ? array_values(array_filter($resolvedItems, 'is_array'))
            : [];
        $sideImage = is_array($resolvedPayload['side_image'] ?? null)
            ? $resolvedPayload['side_image']
            : null;

        if ($isTest) {
            $items = array_map(
                static fn (array $item): array => array_diff_key($item, array_flip(self::LISTENING_SCRIPT_KEYS)),
                array_filter($this->stripAnswers($items), 'is_array'),
            );
        }

        $used = $isTest ? 0 : $this->attemptsUsed($placement, $user);

        return [
            'id' => $placement->id,
            'activityId' => $activity->id,
            'versionId' => $version->id,
            'version' => $version->version,
            'type' => $activity->type->value,
            'label' => $activity->skill_label ?: $activity->type->label(),
            'title' => $activity->title,
            'skillLabel' => $activity->skill_label,
            'prompt' => $placement->effectivePrompt(),
            'promptArabic' => $isTest ? null : $activity->prompt_arabic,
            'description' => $activity->type->hubDescription(),
            'tone' => $activity->type->tone(),
            'icon' => $activity->type->icon(),
            'sideImage' => $sideImage,
            'mode' => $mode,
            'items' => array_values($items),
            'itemCount' => count($items),
            'isAutoScored' => $activity->isAutoScored(),
            'showMeaningEnabled' => ! $isTest && $activity->show_meaning_enabled,
            'timeLimitSeconds' => $activity->time_limit_seconds,
            'attemptsAllowed' => $activity->attempts_allowed,
            'attemptsUsed' => $used,
            'attemptsLeft' => $activity->allowsUnlimitedAttempts() ? null : max(0, $activity->attempts_allowed - $used),
        ];
    }

    /**
     * The version a new attempt must reference, written on the spot if the
     * activity somehow has none (a row inserted around the model events).
     */
    public function currentVersion(Activity $activity): ActivityVersion
    {
        return $activity->currentVersion()->first() ?? $activity->writeCurrentVersion();
    }

    /**
     * Practice attempts this learner has made on this placement.
     */
    public function attemptsUsed(ActivityPlacement $placement, User $user): int
    {
        return Attempt::query()
            ->where('user_id', $user->id)
            ->where('placement_id', $placement->id)
            ->whereNull('test_attempt_id')
            ->count();
    }

    /**
     * The correct answer of every item, keyed by item id, for the result
     * shown AFTER a practice answer (never on a test page).
     *
     * @return array<string, mixed>
     */
    public function answersOf(ActivityVersion $version): array
    {
        $answers = [];

        foreach ($version->items() as $item) {
            $id = (string) ($item['id'] ?? '');

            foreach (self::ANSWER_KEYS as $key) {
                if (array_key_exists($key, $item)) {
                    $answers[$id] = $item[$key];

                    break;
                }
            }
        }

        return $answers;
    }

    /**
     * Remove the answer keys and every Arabic field, recursively.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function stripAnswers(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && (in_array($key, self::ANSWER_KEYS, true) || self::isArabicKey($key))) {
                continue;
            }

            $out[$key] = is_array($value) ? $this->stripAnswers($value) : $value;
        }

        return $out;
    }

    private static function isArabicKey(string $key): bool
    {
        return $key === 'arabic' || str_ends_with($key, '_arabic');
    }
}
