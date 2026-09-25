<?php

namespace App\Http\Controllers\Owner;

use App\Enums\ApiAccount;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\Concerns\ResolvesOwner;
use App\Http\Requests\Owner\ApiKeyRequest;
use App\Models\ApiAccountSetting;
use App\Models\AuditLog;
use App\Services\Owner\ApiCredit;
use App\Services\Owner\DeepgramBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;

/**
 * Replace or clear a paid API account's key (spec 0007, D2). The key is
 * stored encrypted and used by the next job; the audit row keeps only its
 * last four characters (SEC-03, SEC-06).
 */
class ApiKeyController extends Controller
{
    use ResolvesOwner;

    public function update(ApiKeyRequest $request, ApiAccount $account, ApiCredit $credit, DeepgramBalance $deepgram): RedirectResponse
    {
        $key = $request->key();

        $setting = ApiAccountSetting::for($account);
        $setting->forceFill(['api_key' => $key, 'key_updated_at' => Date::now()])->save();

        AuditLog::recordByOwner($setting, 'api_key.replaced', $this->owner($request), [
            'account' => $account->value,
            'ends_with' => mb_substr($key, -4),
        ]);

        $this->forget($account, $credit, $deepgram);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':service key saved. New AI calls use it now.', ['service' => $account->label()])]);

        return back();
    }

    public function destroy(Request $request, ApiAccount $account, ApiCredit $credit, DeepgramBalance $deepgram): RedirectResponse
    {
        $setting = ApiAccountSetting::for($account);

        if ($setting->exists && $setting->key() !== null) {
            $setting->forceFill(['api_key' => null, 'key_updated_at' => Date::now()])->save();
            AuditLog::recordByOwner($setting, 'api_key.cleared', $this->owner($request), ['account' => $account->value]);
        }

        $this->forget($account, $credit, $deepgram);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':service now uses the key from the server .env file.', ['service' => $account->label()])]);

        return back();
    }

    private function forget(ApiAccount $account, ApiCredit $credit, DeepgramBalance $deepgram): void
    {
        $credit->forget();

        if ($account === ApiAccount::Deepgram) {
            // The balance shown was read with the old key.
            $deepgram->forget();
        }
    }
}
