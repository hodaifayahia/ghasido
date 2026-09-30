<x-mail-frame
    :title="__('Your GHASIDO email works')"
    :eyebrow="__('Email settings')"
    :preheader="__('This test email was sent from the Email settings page. If you can read it, emails are being delivered.')"
>
    <x-mail.p>{{ __('This test email was sent from the Email settings page. If you can read it, emails are being delivered.') }}</x-mail.p>
    <x-mail.details>
        <x-mail.detail :label="__('Sent through')">{{ $server }}</x-mail.detail>
        <x-mail.detail :label="__('From')">{{ $from }}</x-mail.detail>
        <x-mail.detail :label="__('Sent at')" :last="true">{{ $sentAt }}</x-mail.detail>
    </x-mail.details>
    <x-mail.note tone="success">{{ __('Tip: check that this email reached the inbox, not the spam folder. If it went to spam, see the DNS steps in the email setup guide.') }}</x-mail.note>
</x-mail-frame>
