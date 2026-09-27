<x-mail-frame :title="__('We could not confirm your GHASIDO payment')">
    <p style="margin: 0 0 16px;">{{ __('Hello :name,', ['name' => $name]) }}</p>
    <p style="margin: 0 0 16px;">{{ __('We could not confirm the payment sent for :account, so the subscription has not been activated.', ['account' => $accountName]) }}</p>
    <p style="margin: 0 0 16px; padding: 12px 14px; background: #fdecec; border-radius: 10px; color: #b42318;">{{ $reason }}</p>
    <p style="margin: 0;">{{ __('Please reply to this email or contact us to send a valid payment, and we will activate your account.') }}</p>
</x-mail-frame>
