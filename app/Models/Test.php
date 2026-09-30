<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\GenerationStatus;
use App\Enums\ResultsVisibility;
use App\Enums\TestType;
use App\Policies\TestPolicy;
use Database\Factories\TestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A Pre-test or Post-test (TEST-01, TEST-02, TSTM-02, spec 0003 B.6).
 *
 * Its questions are `activity_placements` rows pointing here, so a question
 * is the same thing as a lesson practice item and every answer lands in
 * `attempts` with the version it referred to (PRAC-05, TEST-09).
 *
 * Everything the runner needs at run time comes from `settings`: the time
 * limit, shuffling, what the learner may see afterwards, the pass score and
 * what happens on timeout (TEST-04, TIME-01). None of it is a constant.
 *
 * @property int $id
 * @property TestType $type
 * @property int|null $department_id null = all departments
 * @property int|null $hotel_id
 * @property int|null $paired_test_id
 * @property string $title
 * @property array<string, mixed>|null $intro
 * @property array<string, mixed>|null $settings
 * @property ContentStatus $status
 * @property GenerationStatus|null $ai_status
 * @property string|null $ai_failed_reason
 * @property array<string, mixed>|null $ai_request
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'type',
    'department_id',
    'hotel_id',
    'paired_test_id',
    'title',
    'intro',
    'settings',
    'status',
    'ai_status',
    'ai_failed_reason',
    'ai_request',
])]
#[UsePolicy(TestPolicy::class)]
class Test extends Model
{
    /** @use HasFactory<TestFactory> */
    use HasFactory;

    /** `activity_placements.overrides` flag on an unreleased AI draft (GEN-03). */
    public const AI_DRAFT_OVERRIDE = 'ai_draft';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TestType::class,
            'intro' => 'array',
            'settings' => 'array',
            'status' => ContentStatus::class,
            'ai_status' => GenerationStatus::class,
            'ai_request' => 'array',
        ];
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Hotel, $this> */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /** @return BelongsTo<Test, $this> */
    public function pairedTest(): BelongsTo
    {
        return $this->belongsTo(Test::class, 'paired_test_id');
    }

    /**
     * The questions, in order. Each is an activity placed on this test; the
     * activity itself may also sit in a lesson (PRAC-05, WRITE-05).
     *
     * @return MorphMany<ActivityPlacement, $this>
     */
    public function questions(): MorphMany
    {
        return $this->morphMany(ActivityPlacement::class, 'placeable')->orderBy('position');
    }

    /**
     * The questions a learner sits: every placement except AI-drafted ones
     * the admin has not released yet (GEN-03). Publishing the test releases
     * them (TestService::publish).
     *
     * @return Collection<int, ActivityPlacement>
     */
    public function learnerQuestions(): Collection
    {
        return $this->questions()
            ->with('activity')
            ->get()
            ->reject(fn (ActivityPlacement $placement): bool => self::isAiDraft($placement))
            ->values();
    }

    /**
     * Is this placement an AI draft still awaiting review (GEN-03)?
     */
    public static function isAiDraft(ActivityPlacement $placement): bool
    {
        return ($placement->overrides[self::AI_DRAFT_OVERRIDE] ?? false) === true;
    }

    /** @return HasMany<TestAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    // ----------------------------------------------------------------- scopes

    /**
     * The tests this employee may sit: published, for their department, and
     * either shared across hotels or belonging to theirs (ROLE-02, ORG-04,
     * JOURNEY-03).
     *
     * This is the one place that rule is written. The policy, the home page
     * and User::hasSubmittedPreTest() all go through it, so they can never
     * disagree. A user with no department matches nothing.
     *
     * @param  Builder<Test>  $query
     */
    public function scopeForLearner(Builder $query, User $user): void
    {
        $departmentId = $user->learningDepartmentId();

        if ($departmentId === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query
            ->where('status', ContentStatus::Published->value)
            // Their department's test, or one for all departments (null).
            ->where(fn (Builder $inner) => $inner->where('department_id', $departmentId)->orWhereNull('department_id'))
            ->where(function (Builder $inner) use ($user): void {
                $inner->whereNull('hotel_id');

                if ($user->hotel_id !== null) {
                    $inner->orWhere('hotel_id', $user->hotel_id);
                }
            });
    }

    /**
     * @param  Builder<Test>  $query
     */
    public function scopeOfType(Builder $query, TestType $type): void
    {
        $query->where('type', $type->value);
    }

    // --------------------------------------------------------------- settings

    /**
     * Seconds allowed for one sitting, or null for no timer (TIME-01).
     *
     * An explicit `time_limit_seconds: null` means untimed. A Pre-test whose
     * settings do not mention a limit at all falls back to the platform
     * default in `config/guesvia.php`, so a seeded test is never accidentally
     * untimed.
     */
    public function timeLimitSeconds(): ?int
    {
        if (array_key_exists('time_limit_seconds', $this->settings ?? [])) {
            $configured = $this->setting('time_limit_seconds');

            if (is_int($configured) || (is_string($configured) && ctype_digit($configured))) {
                return (int) $configured > 0 ? (int) $configured : null;
            }

            return null;
        }

        if ($this->type === TestType::Pre) {
            $default = config('guesvia.tests.pre_test_time_limit_seconds');

            return is_int($default) && $default > 0 ? $default : null;
        }

        return null;
    }

    /**
     * What the learner sees after submitting (TEST-04). Unset means hidden:
     * never assume results are shown.
     */
    public function resultsVisibility(): ResultsVisibility
    {
        $value = $this->setting('results_visibility');

        return is_string($value)
            ? (ResultsVisibility::tryFrom($value) ?? ResultsVisibility::Hidden)
            : ResultsVisibility::Hidden;
    }

    /**
     * May the learner tap Show Meaning on this test's questions? The client
     * asked for it on Pre- and Post-tests (decision 2026-09-26, overriding
     * CTRL-04's default), so an unset value means yes; the admin can switch
     * it off per test when a sitting must be taken without help.
     */
    public function showsMeaning(): bool
    {
        return (bool) ($this->setting('show_meaning') ?? true);
    }

    public function shufflesQuestions(): bool
    {
        return (bool) ($this->setting('shuffle_questions') ?? false);
    }

    /**
     * Show each option-based question's options in a per-sitting order
     * (TestRunner::presentOptions). Option ids never change, so a stored
     * raw answer still names the same option (TEST-06).
     */
    public function shufflesOptions(): bool
    {
        return (bool) ($this->setting('shuffle_options') ?? false);
    }

    /**
     * Only one sitting may be submitted: once one is, the learner cannot
     * start another (TestController::start).
     */
    public function isSingleAttempt(): bool
    {
        return (bool) ($this->setting('single_attempt') ?? false);
    }

    /**
     * List each question with the learner's and the correct answer on the
     * result page, and only there, and only when results are visible at all
     * (TEST-03, TEST-04).
     */
    public function showsAnswers(): bool
    {
        return (bool) ($this->setting('show_answers') ?? false)
            && $this->resultsVisibility() !== ResultsVisibility::Hidden;
    }

    /**
     * End the sitting with a short encouraging message, whatever the
     * results visibility.
     */
    public function showsMotivationalMessage(): bool
    {
        return (bool) ($this->setting('motivational_message') ?? false);
    }

    /**
     * The percentage needed to pass, or null when no pass mark is set.
     */
    public function passScore(): ?float
    {
        $value = $this->setting('pass_score');

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * What happens when the clock runs out: `submit` scores what was answered,
     * `unanswered` marks the sitting expired (TIME-03).
     */
    public function onTimeout(): string
    {
        $value = $this->setting('on_timeout');

        return $value === 'unanswered' ? 'unanswered' : 'submit';
    }

    private function setting(string $key): mixed
    {
        return $this->settings[$key] ?? null;
    }
}
