<?php

namespace App\Http\Controllers\Owner;

use App\Enums\ApiAccount;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\Concerns\ResolvesOwner;
use App\Http\Requests\Owner\CreditTopupRequest;
use App\Models\ApiAccountSetting;
use App\Models\ApiCreditTopup;
use App\Models\AuditLog;
use App\Services\Owner\ApiCredit;
use App\Services\Owner\CreditAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Recharge a paid API account (spec 0007, D4, D10): one ledger row with
 * the dollars the Super Admin is credited and the units they buy, and the
 * account's metering starts on its first recharge (D5). A blocked
 * account is callable again on the next request.
 */
class CreditTopupController extends Controller
{
    use ResolvesOwner;

    public function store(CreditTopupRequest $request, ApiAccount $account, ApiCredit $credit, CreditAlerts $alerts): RedirectResponse
    {
        $owner = $this->owner($request);
        $usd = $request->usd();
        $units = $request->meterAmounts($account);

        DB::transaction(function () use ($account, $owner, $usd, $units, $request): void {
            $setting = ApiAccountSetting::for($account);

            if ($setting->metering_started_at === null) {
                $setting->forceFill(['metering_started_at' => Date::now()]);
            }

            $setting->save();

            $topup = ApiCreditTopup::query()->create([
                'account' => $account,
                'amount_usd' => $usd,
                ...$units,
                'note' => $request->note(),
                'owner_id' => $owner->id,
            ]);

            AuditLog::recordByOwner($topup, 'api_credit.recharged', $owner, [
                'account' => $account->value,
                'usd' => $usd,
                ...$units,
            ]);
        });

        $credit->forget();
        // A recharge re-arms the low and used-up emails (D12).
        $alerts->reset($account);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':service credit updated.', ['service' => $account->label()])]);

        return back();
    }
}
