<?php

namespace Tests\Feature\Owner;

use App\Contracts\AiUsageInfo;
use App\Enums\AccountStatus;
use App\Enums\AiFeature;
use App\Enums\ApiAccount;
use App\Mail\ApiCreditAlertMail;
use App\Models\ApiAccountSetting;
use App\Models\ApiCreditTopup;
use App\Models\Owner;
use App\Models\User;
use App\Services\Ai\UsageMeter;
use App\Services\Owner\CreditSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The low-credit and used-up emails (spec 0007, D12): to the owner and
 * every active Super Admin, once each, re-armed by a recharge.
 */
class CreditAlertsTest extends TestCase
{
    use RefreshDatabase;

    private Owner $owner;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->owner = Owner::factory()->create(['email' => 'owner@clickdz.test']);
        $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'client@research.test']);

        ApiAccountSetting::for(ApiAccount::Qwen)->forceFill(['metering_started_at' => Date::now()->subMinute()])->save();
        ApiCreditTopup::query()->create(['account' => ApiAccount::Qwen, 'amount_usd' => 200, 'amount_tokens' => 1000]);
    }

    private function useTokens(int $tokens): void
    {
        app(UsageMeter::class)->record(null, AiFeature::LessonGenerate, new AiUsageInfo($tokens, 0, 'qwen3.8-flash', 'qwen'), chargePoints: false);
    }

    public function test_the_owner_and_the_super_admin_are_warned_once_at_twenty_percent()
    {
        $this->useTokens(700);
        Mail::assertNothingQueued();

        $this->useTokens(150);
        Mail::assertQueued(ApiCreditAlertMail::class, 2);
        Mail::assertQueued(ApiCreditAlertMail::class, fn (ApiCreditAlertMail $mail): bool => $mail->hasTo('owner@clickdz.test') && $mail->forOwner && $mail->level === ApiCreditAlertMail::LOW);
        Mail::assertQueued(ApiCreditAlertMail::class, fn (ApiCreditAlertMail $mail): bool => $mail->hasTo('client@research.test') && ! $mail->forOwner);

        // Still low: no second "running low" email.
        $this->useTokens(50);
        Mail::assertQueued(ApiCreditAlertMail::class, 2);
    }

    public function test_a_used_up_account_sends_the_used_up_email_once()
    {
        $this->useTokens(1000);

        // Straight to empty: the "used up" email only, never "low" after it.
        Mail::assertQueued(ApiCreditAlertMail::class, 2);
        Mail::assertQueued(ApiCreditAlertMail::class, fn (ApiCreditAlertMail $mail): bool => $mail->level === ApiCreditAlertMail::EMPTY);

        $this->useTokens(10);
        Mail::assertQueued(ApiCreditAlertMail::class, 2);
    }

    public function test_a_recharge_rearms_the_emails()
    {
        $this->useTokens(900);
        Mail::assertQueued(ApiCreditAlertMail::class, 2);

        $this->actingAs($this->owner, 'owner')
            ->post(route('owner.accounts.topups.store', ['account' => 'qwen']), ['usd' => 10, 'tokens' => 100])
            ->assertSessionHasNoErrors();

        $setting = ApiAccountSetting::for(ApiAccount::Qwen);
        $this->assertNull($setting->low_alert_sent_at);
        $this->assertNull($setting->empty_alert_sent_at);

        // 1,100 granted, 990 used after this: 10% left, low again.
        $this->useTokens(90);
        Mail::assertQueued(ApiCreditAlertMail::class, 4);
    }

    public function test_only_active_super_admins_are_emailed()
    {
        User::factory()->superAdmin()->create(['email' => 'gone@research.test', 'status' => AccountStatus::Inactive]);
        User::factory()->admin()->create(['email' => 'hotel-admin@hotel.test']);
        User::factory()->employee()->create(['email' => 'learner@hotel.test']);

        $this->useTokens(900);

        Mail::assertQueued(ApiCreditAlertMail::class, 2);
        Mail::assertNotQueued(ApiCreditAlertMail::class, fn (ApiCreditAlertMail $mail): bool => $mail->hasTo('gone@research.test') || $mail->hasTo('hotel-admin@hotel.test') || $mail->hasTo('learner@hotel.test'));
    }

    public function test_an_account_without_a_limit_never_alerts()
    {
        $this->useTokens(0);
        app(UsageMeter::class)->record(null, AiFeature::Tts, new AiUsageInfo(100000, 0, 'aura-2-thalia-en', 'deepgram'), chargePoints: false);

        Mail::assertNothingQueued();
    }

    public function test_the_email_says_what_is_left_and_where_to_go()
    {
        $this->useTokens(900);
        $summary = app(CreditSummary::class)->present(ApiAccount::Qwen);

        $forAdmin = (new ApiCreditAlertMail(ApiCreditAlertMail::LOW, $summary, false, 'Dr Amina'))->render();
        $this->assertStringContainsString('Dr Amina', $forAdmin);
        $this->assertStringContainsString('$20.00 left of $200.00', $forAdmin);
        $this->assertStringContainsString('100 of 1,000 tokens left', $forAdmin);
        $this->assertStringContainsString('ask the platform owner', $forAdmin);
        $this->assertStringContainsString(route('dashboard'), $forAdmin);

        $forOwner = (new ApiCreditAlertMail(ApiCreditAlertMail::EMPTY, $summary, true, 'Owner'))->render();
        $this->assertStringContainsString('has run out', $forOwner);
        $this->assertStringContainsString(route('owner.dashboard'), $forOwner);
    }
}
