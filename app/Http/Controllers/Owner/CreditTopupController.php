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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Recharge a paid API account (spec 0007, D4): one ledger row, and the
 * account's metering starts on its first recharge (D5). A blocked
 * account is callable again on the next request.
 */
class CreditTopupController extends Controller
{
    use ResolvesOwner;

    public function store(CreditTopupRequest $request, ApiAccount $account, ApiCredit $credit): RedirectResponse
    {
        $owner = $this->owner($request);
        $usd = $request->usd();
        $tokens = $account->tracksTokens() ? $request->tokens() : 0;

        DB::transaction(function () use ($account, $owner, $usd, $tokens, $request): void {
            $setting = ApiAccountSetting::for($account);

            if ($setting->metering_started_at === null) {
                $setting->forceFill(['metering_started_at' => Date::now()]);
            }

            $setting->save();

            $topup = ApiCreditTopup::query()->create([
                'account' => $account,
                'amount_usd' => $usd,
                'amount_tokens' => $tokens,
                'note' => $request->note(),
                'owner_id' => $owner->id,
            ]);

            AuditLog::recordByOwner($topup, 'api_credit.recharged', $owner, [
                'account' => $account->value,
                'usd' => $usd,
                'tokens' => $tokens,
            ]);
        });

        $credit->forget();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':service credit updated.', ['service' => $account->label()])]);

        return back();
    }
}
