<?php

namespace App\Services\Payments;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Mail\PaymentSubmittedMail;
use App\Models\PaymentSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells every active Super Admin that a customer has sent a payment to
 * review (client request 2026-09-27). The bell and the Payments badge show
 * it too; the email is so nobody has to keep the dashboard open.
 */
final class PaymentNotifier
{
    public function submitted(PaymentSubmission $submission): void
    {
        $admins = User::query()
            ->where('status', AccountStatus::Active->value)
            ->whereNotNull('email')
            ->whereHas('roles', fn ($roles) => $roles->where('name', Role::SuperAdmin->value))
            ->get();

        foreach ($admins as $admin) {
            try {
                Mail::to((string) $admin->email, $admin->name)
                    ->locale($admin->locale ?? 'en')
                    ->queue(new PaymentSubmittedMail($submission));
            } catch (Throwable $exception) {
                // A mail outage must never lose the customer's payment.
                Log::warning('Payment notification could not be queued.', [
                    'submission' => $submission->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
