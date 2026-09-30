<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\IndividualSubscription;
use App\Models\PaymentSubmission;
use App\Models\SubscriptionPaymentMethod;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Manual payments sent from the checkout (client request 2026-09-27). The
 * customer pays outside the platform, then sends the method, the amount, the
 * transaction reference and a receipt. The Super Admin checks it and approves
 * or rejects the account; the submission follows that decision.
 */
final class PaymentSubmissionService
{
    /**
     * Record a payment for a new hotel or individual subscription. Call it
     * inside the transaction that created the account.
     *
     * @param  array{currency: 'DZD'|'USD', reference: string|null, payer_name: string, payer_email: string, payer_phone: string|null}  $payment
     */
    public function submit(
        Hotel|IndividualSubscription $account,
        SubscriptionPlan $plan,
        ?SubscriptionPaymentMethod $method,
        array $payment,
        ?UploadedFile $proof,
    ): PaymentSubmission {
        $submission = new PaymentSubmission([
            'public_id' => (string) Str::uuid(),
            'hotel_id' => $account instanceof Hotel ? $account->id : null,
            'individual_subscription_id' => $account instanceof IndividualSubscription ? $account->id : null,
            'subscription_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'currency' => $payment['currency'],
            'amount' => $payment['currency'] === 'USD' ? $plan->price_usd : $plan->price_dzd,
            'payment_method_id' => $method?->id,
            // Empty when no method was set up; shown as "Not specified".
            'payment_method_name' => $method->name ?? '',
            'reference' => $payment['reference'],
            'payer_name' => $payment['payer_name'],
            'payer_email' => $payment['payer_email'],
            'payer_phone' => $payment['payer_phone'],
            'status' => PaymentStatus::Pending,
        ]);

        if ($proof !== null) {
            $extension = strtolower($proof->guessExtension() ?? $proof->getClientOriginalExtension());
            $path = Storage::disk(PaymentSubmission::PROOF_DISK)->putFileAs(
                'payment-proofs/'.Date::now()->format('Y/m'),
                $proof,
                Str::uuid().'.'.$extension,
            );

            // A receipt that cannot be saved stops the checkout, rather
            // than a payment quietly arriving without it.
            if ($path === false) {
                throw ValidationException::withMessages([
                    'proof' => __('We could not save your receipt. Please try again.'),
                ]);
            }

            $submission->fill([
                'proof_path' => $path,
                // Only a display name; the stored file is always a UUID.
                'proof_name' => Str::limit($proof->getClientOriginalName(), 180, ''),
                'proof_mime' => $proof->getMimeType(),
                'proof_size' => $proof->getSize(),
            ]);
        }

        $submission->save();

        AuditLog::record($submission, 'payment.submitted', [
            'account' => $account instanceof Hotel ? 'hotel' : 'individual',
            'account_id' => $account->id,
            'plan' => $plan->name,
            'amount' => $submission->amount,
            'currency' => $submission->currency,
            'method' => $method?->name,
            'has_receipt' => $submission->proof_path !== null,
        ]);

        return $submission;
    }

    /**
     * The account was approved or rejected: its waiting payments follow.
     */
    public function settle(Hotel|IndividualSubscription $account, PaymentStatus $status, User $reviewer, ?string $reason = null): int
    {
        $pending = PaymentSubmission::query()
            ->pending()
            ->when(
                $account instanceof Hotel,
                fn ($q) => $q->where('hotel_id', $account->id),
                fn ($q) => $q->where('individual_subscription_id', $account->id),
            )
            ->get();

        foreach ($pending as $submission) {
            $submission->fill([
                'status' => $status,
                'reviewed_at' => Date::now(),
                'reviewed_by' => $reviewer->id,
                'rejection_reason' => $status === PaymentStatus::Rejected ? $reason : null,
            ]);

            AuditLog::record($submission, $status === PaymentStatus::Confirmed ? 'payment.confirmed' : 'payment.rejected');
            $submission->save();
        }

        return $pending->count();
    }
}
