<?php

namespace App\Services\Owner;

use App\Enums\AccountStatus;
use App\Enums\ApiAccount;
use App\Enums\Role;
use App\Mail\ApiCreditAlertMail;
use App\Models\ApiAccountSetting;
use App\Models\Owner;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;

/**
 * Warns before the AI stops (spec 0007, D12): when an account's credit
 * drops under 20% left, and again when it is used up, an email goes to the
 * platform owner and to every active Super Admin, so learners in the
 * research study are not cut off mid-lesson.
 *
 * Checked after every metered call. Each email goes out once: the flag is
 * claimed with one conditional UPDATE, so two jobs finishing together
 * cannot both send it, and a recharge clears the flags.
 */
final class CreditAlerts
{
    public function __construct(
        private readonly ApiCredit $credit,
        private readonly CreditSummary $summary,
    ) {}

    /**
     * After a usage row for this provider was written.
     */
    public function afterUsage(string $provider): void
    {
        $account = ApiAccount::forProvider($provider);

        if ($account !== null) {
            $this->check($account);
        }
    }

    public function check(ApiAccount $account): void
    {
        // Fresh figures: this runs right after new usage was written.
        $this->credit->forget();
        $balance = $this->credit->balance($account);

        if (! $balance->isLimited()) {
            return;
        }

        if ($balance->exhausted()) {
            if ($this->claim($account, 'empty_alert_sent_at')) {
                $this->send($account, ApiCreditAlertMail::EMPTY);
            }

            return;
        }

        if ($balance->low() && $this->claim($account, 'low_alert_sent_at')) {
            $this->send($account, ApiCreditAlertMail::LOW);
        }
    }

    /**
     * A recharge re-arms both emails.
     */
    public function reset(ApiAccount $account): void
    {
        ApiAccountSetting::query()
            ->where('account', $account->value)
            ->update(['low_alert_sent_at' => null, 'empty_alert_sent_at' => null]);
    }

    /**
     * Mark the alert sent; true only for the one caller that set it.
     */
    private function claim(ApiAccount $account, string $column): bool
    {
        $values = [$column => Date::now()];

        if ($column === 'empty_alert_sent_at') {
            // Used up implies low: never send "low" after "used up".
            ApiAccountSetting::query()
                ->where('account', $account->value)
                ->whereNull('low_alert_sent_at')
                ->update(['low_alert_sent_at' => Date::now()]);
        }

        return ApiAccountSetting::query()
            ->where('account', $account->value)
            ->whereNull($column)
            ->update($values) === 1;
    }

    private function send(ApiAccount $account, string $level): void
    {
        $summary = $this->summary->present($account);

        foreach (Owner::query()->get() as $owner) {
            Mail::to($owner->email, $owner->name)
                ->queue(new ApiCreditAlertMail($level, $summary, true, $owner->name));
        }

        // Through the relation: User::role() is the model's own `role`
        // accessor, which shadows the permission package's query scope.
        $admins = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', Role::SuperAdmin->value))
            ->where('status', AccountStatus::Active->value)
            ->whereNotNull('email')
            ->get();

        foreach ($admins as $admin) {
            if ($admin->email === null || trim($admin->email) === '') {
                continue;
            }

            Mail::to($admin->email, $admin->name)
                ->queue(new ApiCreditAlertMail($level, $summary, false, $admin->name));
        }
    }
}
