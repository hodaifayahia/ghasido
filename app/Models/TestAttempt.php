<?php

namespace App\Models;

use App\Enums\TestAttemptStatus;
use App\Policies\TestAttemptPolicy;
use Database\Factories\TestAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * One sitting of a Pre-test or Post-test (TEST-05, TIME-05, spec 0003 B.6).
 *
 * The clock lives here: `started_at` and `deadline_at` are server timestamps
 * written when the sitting is created, and `remainingSeconds()` is computed
 * from them on every request, so a refresh cannot reset a timer (TIME-05).
 * The per-question answers are `attempts` rows keyed on this one; only the
 * totals are stored here (TEST-06).
 *
 * @property int $id
 * @property int $user_id
 * @property int $test_id
 * @property int $attempt_no
 * @property TestAttemptStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $deadline_at
 * @property Carbon|null $submitted_at
 * @property string|null $score
 * @property string|null $max_score
 * @property array<string, mixed>|null $breakdown
 * @property Carbon|null $results_released_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'test_id',
    'attempt_no',
    'status',
    'started_at',
    'deadline_at',
    'submitted_at',
    'score',
    'max_score',
    'breakdown',
    'results_released_at',
])]
#[UsePolicy(TestAttemptPolicy::class)]
class TestAttempt extends Model
{
    /** @use HasFactory<TestAttemptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_no' => 'integer',
            'status' => TestAttemptStatus::class,
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'breakdown' => 'array',
            'results_released_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Test, $this> */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    /**
     * The answers given in this sitting, one per question (TEST-06).
     *
     * @return HasMany<Attempt, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    // ------------------------------------------------------------------ clock

    /**
     * Has the deadline passed, or has the sitting already been marked expired?
     *
     * A sitting with no deadline never expires. A submitted sitting is over,
     * not expired.
     */
    public function isExpired(): bool
    {
        if ($this->status === TestAttemptStatus::Expired) {
            return true;
        }

        if ($this->status !== TestAttemptStatus::InProgress || $this->deadline_at === null) {
            return false;
        }

        return Date::now()->greaterThanOrEqualTo($this->deadline_at);
    }

    /**
     * Whole seconds left on the server clock, floored at zero; null when the
     * test has no time limit (TIME-02, TIME-05).
     */
    public function remainingSeconds(): ?int
    {
        if ($this->deadline_at === null) {
            return null;
        }

        $remaining = (int) Date::now()->diffInSeconds($this->deadline_at, false);

        return max(0, $remaining);
    }

    public function isOpen(): bool
    {
        return $this->status->acceptsAnswers() && ! $this->isExpired();
    }

    // ----------------------------------------------------------------- result

    /**
     * The totals of this sitting, ready for the result page and the reports.
     *
     * `percent` is null until the sitting has been scored; `passed` is null
     * when the test sets no pass mark. What the learner may actually see is
     * decided by Test::resultsVisibility(), not here (TEST-04).
     *
     * @return array{score: float|null, max_score: float|null, percent: int|null, passed: bool|null}
     */
    public function scoreSummary(): array
    {
        $score = $this->score === null ? null : (float) $this->score;
        $maxScore = $this->max_score === null ? null : (float) $this->max_score;

        $percent = $score !== null && $maxScore !== null && $maxScore > 0
            ? (int) round($score / $maxScore * 100)
            : null;

        $passScore = $this->test?->passScore();

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'percent' => $percent,
            'passed' => $percent !== null && $passScore !== null ? $percent >= $passScore : null,
        ];
    }

    public function isSubmitted(): bool
    {
        return $this->status === TestAttemptStatus::Submitted;
    }
}
