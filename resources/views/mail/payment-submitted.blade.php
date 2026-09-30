<x-mail-frame
    :title="__('New payment to review')"
    :eyebrow="__('Payments')"
    :preheader="__(':customer sent a payment for the :plan plan (:amount).', ['customer' => $customer, 'plan' => $planName, 'amount' => $amount])"
    :signoff="false"
>
    <x-mail.p>{{ __(':customer sent a payment for the :plan plan (:amount).', ['customer' => $customer, 'plan' => $planName, 'amount' => $amount]) }}</x-mail.p>
    <x-mail.details>
        <x-mail.detail :label="__('Customer')">{{ $customer }}</x-mail.detail>
        <x-mail.detail :label="__('Plan')">{{ $planName }}</x-mail.detail>
        <x-mail.detail :label="__('Amount')">{{ $amount }}</x-mail.detail>
        <x-mail.detail :label="__('Method')" :last="! $reference">{{ $method }}</x-mail.detail>
        @if ($reference)
            <x-mail.detail :label="__('Reference')" :last="true">{{ $reference }}</x-mail.detail>
        @endif
    </x-mail.details>
    <x-mail.button :href="$reviewUrl">{{ __('Review the payment') }}</x-mail.button>
</x-mail-frame>
