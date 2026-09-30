<x-mail-frame
    :title="__('We could not confirm your GHASIDO payment')"
    :eyebrow="__('Payment')"
    :preheader="__('We could not confirm the payment sent for :account, so the subscription has not been activated.', ['account' => $accountName])"
>
    <x-mail.p>{{ __('Hello :name,', ['name' => $name]) }}</x-mail.p>
    <x-mail.p>{{ __('We could not confirm the payment sent for :account, so the subscription has not been activated.', ['account' => $accountName]) }}</x-mail.p>
    <x-mail.note tone="danger"><strong>{{ __('Reason') }}:</strong> <span dir="auto">{{ $reason }}</span></x-mail.note>
    <x-mail.p :last="true">{{ __('Please reply to this email or contact us to send a valid payment, and we will activate your account.') }}</x-mail.p>
</x-mail-frame>
