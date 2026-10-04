<?php

namespace App\Services\Learning;

use App\Enums\TestAttemptStatus;
use App\Enums\TestType;
use App\Models\LevelChangeRequest;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The learner's level is locked until they finish the Post-test of that
 * level (client request 2026-10-04), so their path and its Pre/Post-test
 * results stay meaningful. The first choice is always free; afterwards a
 * submitted Post-test sat since the level was set opens the choice again.
 * Before that, a change goes through a request the Super Admin decides.
 */
final class LevelLock
{
    public function canChange(User $user): bool
    {
        if ($user->english_level === null) {
            return true;
        }

        return $user->testAttempts()
            ->where('status', TestAttemptStatus::Submitted->value)
            ->when(
                $user->english_level_assessed_at !== null,
                fn (Builder $query) => $query->where('submitted_at', '>=', $user->english_level_assessed_at),
            )
            ->whereHas('test', function (Builder $test) use ($user): void {
                /** @var Builder<Test> $test */
                $test->ofType(TestType::Post)->forLearner($user);
            })
            ->exists();
    }

    public function pendingRequest(User $user): ?LevelChangeRequest
    {
        return LevelChangeRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }
}
