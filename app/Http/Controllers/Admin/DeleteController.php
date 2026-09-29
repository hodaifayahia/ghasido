<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityPlacement;
use App\Models\AiInsight;
use App\Models\AiScenario;
use App\Models\AutomationRule;
use App\Models\Block;
use App\Models\ContactMessage;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\Lesson;
use App\Models\ReminderTemplate;
use App\Models\SubscriptionPaymentMethod;
use App\Models\SubscriptionPlan;
use App\Models\Test;
use App\Models\User;
use App\Services\Deletion\DeletionGuards;
use App\Services\Deletion\SafeDelete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Delete button of every admin table (owner decision 2026-09-27, "safe
 * delete"). Each action authorizes like the table's other actions, asks
 * DeletionGuards what still depends on the row, and either refuses with one
 * sentence (a `delete` validation error the dialog shows in place) or
 * deletes the row with its own configuration and writes one audit row.
 *
 * Nothing that holds learner or research data is ever removed (DATA-10):
 * sent reminders, reports and exports have no Delete at all.
 */
class DeleteController extends Controller
{
    public function hotel(Hotel $hotel): RedirectResponse
    {
        Gate::authorize('delete', $hotel);

        SafeDelete::run(
            $hotel,
            $hotel->name,
            DeletionGuards::hotel($hotel),
            __('Archive it instead: access stops and everything is kept.'),
            'hotel.deleted',
            function () use ($hotel): void {
                DB::table('hotel_ai_point_top_up_requests')->where('hotel_id', $hotel->id)->delete();
                self::forgetInsights(Hotel::class, $hotel->id);
            },
        );

        return $this->deleted($hotel->name);
    }

    public function department(Department $department): RedirectResponse
    {
        Gate::authorize('delete', $department);

        SafeDelete::run(
            $department,
            $department->name,
            DeletionGuards::department($department),
            __('Archive it instead: it leaves the lists and everything is kept.'),
            'department.deleted',
        );

        return $this->deleted($department->name);
    }

    public function employee(User $employee): RedirectResponse
    {
        abort_unless($employee->hasRole(Role::Employee->value), 404);

        // The Super Admin's employee list also shows individual subscribers
        // (no hotel): those follow the Individuals page's capability.
        if ($employee->hotel_id === null) {
            abort_unless($employee->isIndividual(), 404);
            Gate::authorize(Permission::SubscriptionsManage->value);
        } else {
            Gate::authorize('delete', $employee);
        }

        $this->deleteAccount($employee, 'employee.deleted', __('Deactivate the account instead: it keeps all their training records.'));

        return $this->deleted($employee->name);
    }

    public function individual(User $individual): RedirectResponse
    {
        abort_unless($individual->isIndividual(), 404);

        $this->deleteAccount($individual, 'individual.deleted', __('Deactivate the account instead: it keeps all their training records.'));

        return $this->deleted($individual->name);
    }

    /** A back-office account (Users page): the same guards as editing one. */
    public function user(Request $request, User $user): RedirectResponse
    {
        Gate::authorize(Permission::UsersManage->value);
        abort_if($user->hasRole(Role::Employee->value), 404);

        /** @var User $actor */
        $actor = $request->user('web');

        if ($user->is($actor)) {
            throw ValidationException::withMessages(['delete' => __('You cannot delete your own account.')]);
        }

        abort_if(
            $user->hasRole(Role::SuperAdmin->value) && ! $actor->hasRole(Role::SuperAdmin->value),
            403,
            __('Only a Super Admin can change another Super Admin account.'),
        );

        $lastSuperAdmin = $user->hasRole(Role::SuperAdmin->value)
            && $user->status === AccountStatus::Active
            && User::query()->whereHas('roles', fn ($query) => $query->where('name', Role::SuperAdmin->value))
                ->where('status', AccountStatus::Active->value)
                ->count() <= 1;

        if ($lastSuperAdmin) {
            throw ValidationException::withMessages(['delete' => __('Keep at least one active Super Admin account.')]);
        }

        $this->deleteAccount(
            $user,
            'user.deleted',
            __('Deactivate the account instead: it keeps all their records.'),
            learner: false,
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->deleted($user->name);
    }

    public function lesson(Lesson $lesson): RedirectResponse
    {
        Gate::authorize('delete', $lesson);

        SafeDelete::run(
            $lesson,
            $lesson->title,
            DeletionGuards::lesson($lesson),
            __('Move it back to draft instead: learners stop seeing it and their progress is kept.'),
            'lesson.deleted',
            function () use ($lesson): void {
                // Placements point at blocks through a morph with no foreign
                // key, so they go first; blocks and their pivots cascade.
                $blockIds = $lesson->blocks()->pluck('id')->all();

                if ($blockIds !== []) {
                    ActivityPlacement::query()
                        ->where('placeable_type', Block::class)
                        ->whereIn('placeable_id', $blockIds)
                        ->delete();
                }
            },
        );

        return $this->deleted($lesson->title);
    }

    public function scenario(AiScenario $scenario): RedirectResponse
    {
        Gate::authorize('delete', $scenario);

        SafeDelete::run(
            $scenario,
            $scenario->title,
            DeletionGuards::scenario($scenario),
            __('Remove it from those lessons first, or keep it as a draft.'),
            'ai_scenario.deleted',
            function () use ($scenario): void {
                // Only draft lessons can still offer it here: take it off
                // their role-play steps, pivot and settings list alike.
                foreach (DeletionGuards::roleplayBlocksOffering($scenario) as $block) {
                    $block->scenarios()->detach($scenario->id);
                    $ids = array_values(array_filter(
                        array_map('intval', (array) $block->setting('scenario_ids', [])),
                        fn (int $id): bool => $id !== $scenario->id,
                    ));
                    $block->settings = [...($block->settings ?? []), 'scenario_ids' => $ids];
                    $block->save();
                }

                // Admin previews are not learner records.
                DB::table('roleplay_attempts')->where('ai_scenario_id', $scenario->id)->where('is_preview', true)->delete();
            },
        );

        return $this->deleted($scenario->title);
    }

    public function test(Test $test): RedirectResponse
    {
        Gate::authorize('delete', $test);

        SafeDelete::run(
            $test,
            $test->title,
            DeletionGuards::test($test),
            __('Their answers are research data, so the test is kept.'),
            'test.deleted',
            function () use ($test): void {
                $placements = ActivityPlacement::query()
                    ->where('placeable_type', Test::class)
                    ->where('placeable_id', $test->id)
                    ->get();
                $activityIds = $placements->pluck('activity_id')->unique()->all();

                ActivityPlacement::query()->whereKey($placements->modelKeys())->delete();

                // A question used nowhere else and never answered goes too.
                foreach ($activityIds as $activityId) {
                    $placedElsewhere = ActivityPlacement::query()->where('activity_id', $activityId)->exists();
                    $answered = DB::table('attempts')->where('activity_id', $activityId)->exists();

                    if (! $placedElsewhere && ! $answered) {
                        Activity::query()->whereKey($activityId)->delete();
                    }
                }
            },
        );

        return $this->deleted($test->title);
    }

    public function plan(SubscriptionPlan $plan): RedirectResponse
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $lastActive = $plan->is_active && SubscriptionPlan::query()->where('is_active', true)->count() <= 1;

        if ($lastActive) {
            throw ValidationException::withMessages(['delete' => __('Keep at least one active plan.')]);
        }

        SafeDelete::run(
            $plan,
            $plan->name,
            ['hotel' => Hotel::query()->withoutGlobalScopes()->where('subscription_plan_id', $plan->id)->count()],
            __('Move those hotels to another plan, or mark the plan Inactive.'),
            'subscription.plan_deleted',
        );

        return $this->deleted($plan->name);
    }

    public function paymentMethod(SubscriptionPaymentMethod $paymentMethod): RedirectResponse
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        SafeDelete::run(
            $paymentMethod,
            $paymentMethod->name,
            ['recorded payment' => DB::table('hotel_ai_point_top_ups')->where('payment_method_id', $paymentMethod->id)->count()],
            __('Turn off Visible instead to hide it.'),
            'subscription.payment_method_deleted',
        );

        return $this->deleted($paymentMethod->name);
    }

    public function template(ReminderTemplate $template): RedirectResponse
    {
        Gate::authorize('delete', $template);

        SafeDelete::run(
            $template,
            $template->name,
            [
                'automation rule' => AutomationRule::query()->where('template_id', $template->id)->count(),
                'sent reminder' => DB::table('reminders')->where('template_id', $template->id)->count(),
            ],
            __('Mark it Inactive instead.'),
            'reminder_template.deleted',
        );

        return $this->deleted($template->name);
    }

    public function rule(AutomationRule $rule): RedirectResponse
    {
        Gate::authorize('delete', $rule);

        SafeDelete::run(
            $rule,
            $rule->name,
            ['sent reminder' => DB::table('reminders')->where('automation_rule_id', $rule->id)->count()],
            __('Pause it instead.'),
            'automation_rule.deleted',
        );

        return $this->deleted($rule->name);
    }

    public function contactMessage(ContactMessage $contactMessage): RedirectResponse
    {
        Gate::authorize(Permission::LandingManage->value);

        SafeDelete::run(
            $contactMessage,
            __('The message from :name', ['name' => $contactMessage->name]),
            [],
            '',
            'contact_message.deleted',
        );

        return $this->deleted(__('The message from :name', ['name' => $contactMessage->name]));
    }

    /**
     * An account (employee, individual or back-office): refused while it
     * holds training records; otherwise its sessions and cached AI notes go
     * with it. Its passkeys, roles and individual subscription cascade.
     */
    private function deleteAccount(User $user, string $action, string $instead, bool $learner = true): void
    {
        SafeDelete::run(
            $user,
            $user->name,
            DeletionGuards::trainingRecords($user, $learner),
            $instead,
            $action,
            function () use ($user): void {
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }

                self::forgetInsights(User::class, $user->id);
            },
            person: true,
        );
    }

    private static function forgetInsights(string $type, int $id): void
    {
        AiInsight::query()->where('subject_type', $type)->where('subject_id', $id)->delete();
    }

    private function deleted(string $name): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was deleted.', ['name' => $name])]);

        return back();
    }
}
