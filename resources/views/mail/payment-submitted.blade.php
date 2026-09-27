<x-mail-frame :title="__('New payment to review')">
    <p style="margin: 0 0 16px;">{{ __(':customer sent a payment for the :plan plan (:amount).', ['customer' => $customer, 'plan' => $planName, 'amount' => $amount]) }}</p>
    <p style="margin: 0 0 16px;">{{ __('Method: :method', ['method' => $method]) }}@if ($reference) · {{ __('Reference: :reference', ['reference' => $reference]) }}@endif</p>
    <p style="margin: 0;"><a href="{{ $reviewUrl }}" style="display: inline-block; background: #0b5cff; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 10px; font-weight: bold;">{{ __('Review the payment') }}</a></p>
</x-mail-frame>
