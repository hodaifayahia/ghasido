<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountStatus;
use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\ActivityPlacement;
use App\Models\AiScenario;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\ContactMessage;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\IndividualSubscription;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\SeatQuota;
use App\Models\SubscriptionPaymentMethod;
use App\Models\SubscriptionPlan;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Safe delete on every admin table (owner decision 2026-09-27): a row goes
 * only when nothing that matters depends on it; otherwise the delete is
 * refused with one sentence naming what does, and nothing is removed
 * (DATA-10). A delete writes one audit row.
 */
class SafeDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->superAdmin()->create();
    }

    // ------------------------------------------------------------ hotels

    public function test_a_hotel_with_accounts_is_kept_and_the_reason_is_given()
    {
        $hotel = Hotel::factory()->create(['name' => 'Sofitel Algiers']);
        User::factory()->count(2)->employee()->forHotel($hotel, Department::factory()->create())->create();

        $this->actingAs($this->owner)
            ->from(route('hotels'))
            ->delete(route('hotels.destroy', $hotel))
            ->assertSessionHasErrors(['delete' => 'Sofitel Algiers cannot be deleted because it still has 2 accounts. Archive it instead: access stops and everything is kept.']);

        $this->assertModelExists($hotel);
    }

    public function test_an_empty_hotel_is_deleted_with_its_quotas_and_audited()
    {
        $hotel = Hotel::factory()->create();
        SeatQuota::factory()->create(['hotel_id' => $hotel->id, 'department_id' => Department::factory()->create()->id]);

        $this->actingAs($this->owner)
            ->from(route('hotels'))
            ->delete(route('hotels.destroy', $hotel))
            ->assertRedirect(route('hotels'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('hotels', ['id' => $hotel->id]);
        $this->assertDatabaseMissing('seat_quotas', ['hotel_id' => $hotel->id]);
        $this->assertSame(1, AuditLog::query()->where('action', 'hotel.deleted')->count());
    }

    // ------------------------------------------------------- departments

    public function test_a_department_in_use_is_kept()
    {
        $department = Department::factory()->create(['hotel_id' => null]);
        Course::factory()->forDepartment($department->id)->create();

        $this->actingAs($this->owner)
            ->delete(route('departments.destroy', $department))
            ->assertSessionHasErrors('delete');

        $this->assertModelExists($department);
    }

    public function test_an_unused_department_is_deleted_but_a_manager_cannot_delete_a_shared_one()
    {
        $department = Department::factory()->create(['hotel_id' => null]);
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->manager()->forHotel($hotel, Department::factory()->create())->create();

        $this->actingAs($manager)
            ->delete(route('departments.destroy', $department))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->delete(route('departments.destroy', $department))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($department);
    }

    // --------------------------------------------------------- employees

    public function test_an_employee_with_training_records_is_kept()
    {
        $hotel = Hotel::factory()->create();
        $employee = User::factory()->employee()->forHotel($hotel, Department::factory()->create())->create(['name' => 'Samira Haddad']);
        LessonCompletion::factory()->create(['user_id' => $employee->id]);

        $this->actingAs($this->owner)
            ->delete(route('employees.destroy', $employee))
            ->assertSessionHasErrors(['delete' => 'Samira Haddad cannot be deleted because they still have 1 completed lesson. Deactivate the account instead: it keeps all their training records.']);

        $this->assertModelExists($employee);
    }

    public function test_an_employee_without_records_is_deleted_but_not_by_another_hotels_manager()
    {
        $department = Department::factory()->create();
        $employee = User::factory()->employee()->forHotel(Hotel::factory()->create(), $department)->create();
        $outsider = User::factory()->manager()->forHotel(Hotel::factory()->create(), $department)->create();

        $this->actingAs($outsider)
            ->delete(route('employees.destroy', $employee))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->delete(route('employees.destroy', $employee))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($employee);
        $this->assertSame(1, AuditLog::query()->where('action', 'employee.deleted')->count());
    }

    public function test_an_individual_without_records_is_deleted_with_their_subscription()
    {
        $individual = User::factory()->employee()->create(['hotel_id' => null, 'department_id' => Department::factory()->create()->id]);
        $subscription = IndividualSubscription::factory()->create(['user_id' => $individual->id]);

        $this->actingAs($this->owner)
            ->delete(route('individuals.destroy', $individual))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($individual);
        $this->assertModelMissing($subscription);
    }

    // ------------------------------------------------------------- users

    public function test_nobody_deletes_their_own_account_or_the_last_super_admin()
    {
        $this->actingAs($this->owner)
            ->from(route('users'))
            ->delete(route('users.destroy', $this->owner))
            ->assertSessionHasErrors(['delete' => 'You cannot delete your own account.']);

        $this->assertModelExists($this->owner);

        // Another Super Admin with no records may go; a hotel admin may not
        // touch a Super Admin account at all.
        $other = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->forHotel(Hotel::factory()->create(), Department::factory()->create())->create();

        $this->actingAs($admin)
            ->delete(route('users.destroy', $other))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->delete(route('users.destroy', $other))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($other);
        $this->assertSame(AccountStatus::Active, $this->owner->fresh()?->status);
    }

    public function test_a_back_office_account_without_records_is_deleted()
    {
        $manager = User::factory()->manager()->forHotel(Hotel::factory()->create(), Department::factory()->create())->create();

        $this->actingAs($this->owner)
            ->delete(route('users.destroy', $manager))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($manager);
    }

    // ------------------------------------------------------------ lessons

    public function test_a_lesson_with_learner_progress_is_kept()
    {
        $lesson = $this->lesson();
        LessonCompletion::factory()->create(['lesson_id' => $lesson->id]);

        $this->actingAs($this->owner)
            ->delete(route('lessons.destroy', $lesson))
            ->assertSessionHasErrors('delete');

        $this->assertModelExists($lesson);
    }

    public function test_a_lesson_nobody_started_is_deleted_with_its_steps()
    {
        $lesson = $this->lesson();
        $blockIds = $lesson->blocks()->pluck('id')->all();

        $this->actingAs($this->owner)
            ->delete(route('lessons.destroy', $lesson))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($lesson);
        $this->assertSame(0, DB::table('blocks')->whereIn('id', $blockIds)->count());
        $this->assertSame(0, ActivityPlacement::query()->whereIn('placeable_id', $blockIds)->where('placeable_type', 'App\\Models\\Block')->count());
    }

    // ---------------------------------------------------------- scenarios

    public function test_a_scenario_in_a_published_lesson_is_kept_and_one_in_a_draft_is_detached()
    {
        $lesson = $this->lesson();
        $scenario = AiScenario::factory()->create(['department_id' => $lesson->course->department_id]);
        $block = $lesson->blocks()->where('type', BlockType::AiRoleplay->value)->firstOrFail();
        $block->scenarios()->sync([$scenario->id => ['position' => 1]]);

        $lesson->forceFill(['status' => ContentStatus::Published])->save();

        $this->actingAs($this->owner)
            ->delete(route('ai-scenarios.destroy', $scenario))
            ->assertSessionHasErrors('delete');
        $this->assertModelExists($scenario);

        $lesson->forceFill(['status' => ContentStatus::Draft])->save();

        $this->actingAs($this->owner)
            ->delete(route('ai-scenarios.destroy', $scenario))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($scenario);
        $this->assertSame([], $block->fresh()?->scenarioIds());
    }

    // -------------------------------------------------------------- tests

    public function test_a_test_someone_took_is_kept_and_an_untaken_one_is_deleted()
    {
        $taken = Test::factory()->create();
        TestAttempt::factory()->create(['test_id' => $taken->id]);

        $this->actingAs($this->owner)
            ->delete(route('tests.destroy', $taken))
            ->assertSessionHasErrors('delete');
        $this->assertModelExists($taken);

        $untaken = Test::factory()->create();

        $this->actingAs($this->owner)
            ->delete(route('tests.destroy', $untaken))
            ->assertSessionHasNoErrors();
        $this->assertModelMissing($untaken);
    }

    // ------------------------------------------------------ subscriptions

    public function test_a_plan_with_hotels_and_a_used_payment_method_are_kept()
    {
        $plan = SubscriptionPlan::query()->where('slug', 'standard')->firstOrFail();
        Hotel::factory()->create(['subscription_plan_id' => $plan->id]);

        $this->actingAs($this->owner)
            ->delete(route('subscriptions.plans.destroy', $plan))
            ->assertSessionHasErrors('delete');
        $this->assertModelExists($plan);

        $method = SubscriptionPaymentMethod::query()->create(['name' => 'CCP', 'recipient_name' => 'GHASIDO', 'account_reference' => '123', 'instructions' => '', 'sort_order' => 1, 'is_active' => true]);
        DB::table('hotel_ai_point_top_ups')->insert([
            'hotel_id' => Hotel::factory()->create()->id,
            'month_start' => now()->startOfMonth()->toDateString(),
            'points' => 1000,
            'amount_dzd' => 5000,
            'payment_method_id' => $method->id,
            'received_by' => $this->owner->id,
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->delete(route('subscriptions.payment-methods.destroy', $method))
            ->assertSessionHasErrors('delete');
        $this->assertModelExists($method);
    }

    public function test_an_unused_plan_is_deleted()
    {
        $plan = SubscriptionPlan::query()->where('slug', 'diamond')->firstOrFail();

        $this->actingAs($this->owner)
            ->delete(route('subscriptions.plans.destroy', $plan))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($plan);
    }

    // ----------------------------------------------------------- messages

    public function test_used_templates_and_rules_are_kept_and_unused_ones_deleted()
    {
        $rule = AutomationRule::factory()->create();
        Reminder::factory()->create(['template_id' => $rule->template_id, 'automation_rule_id' => $rule->id]);

        $this->actingAs($this->owner)
            ->delete(route('messages-reminders.templates.destroy', $rule->template_id))
            ->assertSessionHasErrors('delete');
        $this->actingAs($this->owner)
            ->delete(route('messages-reminders.rules.destroy', $rule))
            ->assertSessionHasErrors('delete');

        $unused = ReminderTemplate::factory()->create();

        $this->actingAs($this->owner)
            ->delete(route('messages-reminders.templates.destroy', $unused))
            ->assertSessionHasNoErrors();
        $this->assertModelMissing($unused);
    }

    public function test_a_contact_message_is_deleted()
    {
        $message = ContactMessage::query()->create([
            'name' => 'Karim',
            'email' => 'karim@example.com',
            'message' => 'Hello',
        ]);

        $this->actingAs($this->owner)
            ->delete(route('contact-messages.destroy', $message))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($message);
    }

    private function lesson(): Lesson
    {
        $department = Department::factory()->create(['hotel_id' => null]);
        $course = Course::factory()->forDepartment($department->id)->create();
        $unit = Unit::factory()->create(['course_id' => $course->id]);

        return Lesson::factory()->withBlocks()->create(['unit_id' => $unit->id, 'status' => ContentStatus::Draft]);
    }
}
