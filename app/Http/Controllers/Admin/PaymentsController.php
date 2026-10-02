<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Http\Controllers\Controller;
use App\Mail\CustomerMessageMail;
use App\Models\AuditLog;
use App\Models\PaymentSubmission;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Meaning\RequestedHelperLanguages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Payments sent from the checkout (client request 2026-09-27): who paid,
 * for which plan, how, the reference and the receipt. From here the Super
 * Admin contacts the customer (email, phone, WhatsApp) and opens the hotel
 * or individual to approve it; the payment follows that decision.
 * Behind `subscriptions.manage`.
 */
class PaymentsController extends Controller
{
    private const int PER_PAGE = 15;

    public function index(Request $request): Response
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $status = (string) $request->query('status', PaymentStatus::Pending->value);
        $type = (string) $request->query('type', 'all');
        $search = trim((string) $request->query('search', ''));

        $page = PaymentSubmission::query()
            ->with(['hotel', 'individualSubscription.user'])
            ->when(in_array($status, ['pending', 'confirmed', 'rejected'], true), fn (Builder $q) => $q->where('status', $status))
            ->when($type === 'hotel', fn (Builder $q) => $q->whereNotNull('hotel_id'))
            ->when($type === 'individual', fn (Builder $q) => $q->whereNotNull('individual_subscription_id'))
            ->when($search !== '', function (Builder $q) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $q->where(fn (Builder $inner) => $inner
                    ->where('payer_name', 'like', $like)
                    ->orWhere('payer_email', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhereHas('hotel', fn (Builder $hotel) => $hotel->withoutGlobalScopes()->where('name', 'like', $like)));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $selectedId = $request->integer('payment');
        $selected = $selectedId > 0
            ? PaymentSubmission::query()->with(['hotel', 'individualSubscription.user', 'reviewer'])->find($selectedId)
            : null;

        return Inertia::render('admin/Payments', [
            'payments' => collect($page->items())->map(fn (PaymentSubmission $payment): array => $this->row($payment))->values()->all(),
            'pagination' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
                'from' => $page->firstItem() ?? 0,
                'to' => $page->lastItem() ?? 0,
            ],
            'filters' => ['status' => $status, 'type' => $type, 'search' => $search],
            'counts' => [
                'pending' => PaymentSubmission::query()->pending()->count(),
                'confirmed' => PaymentSubmission::query()->where('status', PaymentStatus::Confirmed->value)->count(),
                'rejected' => PaymentSubmission::query()->where('status', PaymentStatus::Rejected->value)->count(),
            ],
            'selected' => $selected === null ? null : $this->row($selected),
        ]);
    }

    /** The uploaded receipt, private, inline so an image or PDF opens in place. */
    public function receipt(PaymentSubmission $payment): StreamedResponse
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $disk = Storage::disk(PaymentSubmission::PROOF_DISK);
        abort_unless($payment->proof_path !== null && $disk->exists($payment->proof_path), 404);

        return $disk->response($payment->proof_path, $payment->proof_name ?? basename($payment->proof_path), [
            'Content-Type' => $payment->proof_mime ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Seen: it stops counting in the bell. */
    public function read(PaymentSubmission $payment): RedirectResponse
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        if ($payment->read_at === null) {
            $payment->forceFill(['read_at' => now()])->save();
        }

        return back();
    }

    /** Write to the customer about their payment. */
    public function message(Request $request, PaymentSubmission $payment): RedirectResponse
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $recipient = $payment->individualSubscription->user
            ?? User::query()->where('email', $payment->payer_email)->first();
        $locale = $recipient->locale ?? 'en';

        Mail::to($payment->payer_email, $payment->payer_name)
            ->locale($locale)
            ->queue(new CustomerMessageMail($payment->payer_name, (string) $data['subject'], (string) $data['body']));

        // The same message in the customer's account notifications, at once
        // (client request 2026-09-30).
        if ($recipient !== null) {
            Reminder::query()->create([
                'user_id' => $recipient->id,
                'channel' => ReminderChannel::InApp,
                'subject' => (string) $data['subject'],
                'body' => (string) $data['body'],
                'sent_by' => $request->user('web')?->id,
                'status' => ReminderStatus::Sent,
                'sent_at' => now(),
            ]);
        }

        AuditLog::record($payment, 'payment.customer_emailed', ['subject' => $data['subject']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email sent to :email.', ['email' => $payment->payer_email])]);

        return back();
    }

    /** @return array<string, mixed> */
    private function row(PaymentSubmission $payment): array
    {
        $individual = $payment->individualSubscription;
        $individualUser = $individual?->user;
        $hotel = $payment->hotel;

        return [
            'id' => $payment->id,
            'type' => $payment->isForIndividual() ? 'individual' : 'hotel',
            'customer' => $hotel->name ?? $individualUser->name ?? $payment->payer_name,
            'city' => $hotel?->city,
            'payerName' => $payment->payer_name,
            'payerEmail' => $payment->payer_email,
            'payerPhone' => $payment->payer_phone,
            'planName' => $payment->plan_name,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'method' => $payment->methodLabel(),
            'reference' => $payment->reference,
            'receiptUrl' => $payment->proof_path !== null ? route('payments.receipt', $payment) : null,
            'receiptName' => $payment->proof_name,
            'receiptSize' => $payment->proof_size,
            'isImage' => $payment->isImageProof(),
            'status' => $payment->status->value,
            'statusLabel' => $payment->status->label(),
            'rejectionReason' => $payment->rejection_reason,
            'submittedAt' => $payment->created_at?->toIso8601String(),
            'reviewedAt' => $payment->reviewed_at?->toIso8601String(),
            'unread' => $payment->read_at === null,
            // Where the account is approved: the hotel's page, or the
            // individuals list filtered to this person.
            'accountUrl' => $hotel !== null
                ? route('hotels.show', $hotel)
                : ($individualUser !== null ? route('individuals', ['search' => $individualUser->username, 'state' => 'all']) : null),
            'accountState' => $hotel !== null ? $hotel->access_state->value : $individual?->approval_state->value,
            'hotelId' => $hotel?->id,
            'individualId' => $individualUser?->id,
            // The helper languages asked for at sign-up (client request
            // 2026-10-01), with a shortcut to translate into each.
            'helperLanguages' => app(RequestedHelperLanguages::class)->describe(
                $individualUser ?? ($hotel !== null ? User::query()->where('hotel_id', $hotel->id)->orderBy('id')->first() : null),
            ),
        ];
    }
}
