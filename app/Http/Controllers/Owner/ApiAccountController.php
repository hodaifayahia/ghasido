<?php

namespace App\Http\Controllers\Owner;

use App\Enums\ApiAccount;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\Concerns\ResolvesOwner;
use App\Jobs\RunProviderCheck;
use App\Models\ApiAccountSetting;
use App\Models\AuditLog;
use App\Services\Ai\AiModelSettings;
use App\Services\Owner\ApiCredit;
use App\Services\Owner\DeepgramBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;

/**
 * Pause or resume a paid API account, test it with a real (queued) call,
 * and read Deepgram's own balance (spec 0007).
 */
class ApiAccountController extends Controller
{
    use ResolvesOwner;

    /**
     * Stop (or restart) every AI call that uses the account, whatever its
     * credit (D6, D7).
     */
    public function pause(Request $request, ApiAccount $account, ApiCredit $credit): RedirectResponse
    {
        $setting = ApiAccountSetting::for($account);
        $pausing = $setting->paused_at === null;
        $setting->forceFill(['paused_at' => $pausing ? Date::now() : null])->save();

        AuditLog::recordByOwner($setting, $pausing ? 'api_account.paused' : 'api_account.resumed', $this->owner($request), [
            'account' => $account->value,
        ]);

        $credit->forget();

        Inertia::flash('toast', ['type' => 'success', 'message' => $pausing
            ? __(':service is paused: the app makes no calls to it until you resume.', ['service' => $account->label()])
            : __(':service is running again.', ['service' => $account->label()])]);

        return back();
    }

    /**
     * Queue the same connection checks as Settings → AI models (PERF-04):
     * one small real call per capability, metered like any other.
     */
    public function check(ApiAccount $account, AiModelSettings $settings): RedirectResponse
    {
        foreach ($account->checks() as $capability) {
            $settings->recordCheck($capability, ['status' => 'pending']);
            RunProviderCheck::dispatch($capability);
        }

        return back();
    }

    public function balance(DeepgramBalance $deepgram): RedirectResponse
    {
        $result = $deepgram->refresh();

        if (! $result['ok']) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $result['error'] ?? __('Could not read the Deepgram balance.')]);
        }

        return back();
    }
}
