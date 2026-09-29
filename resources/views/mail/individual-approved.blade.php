<x-mail-frame
    :title="__('Your GHASIDO subscription is active')"
    :eyebrow="__('Welcome to GHASIDO')"
    :preheader="__('We have confirmed your payment. Your :plan subscription is now active and you can sign in to start learning.', ['plan' => $planName])"
>
    <x-mail.p>{{ __('Hello :name,', ['name' => $name]) }}</x-mail.p>
    <x-mail.p>{{ __('We have confirmed your payment. Your :plan subscription is now active and you can sign in to start learning.', ['plan' => $planName]) }}</x-mail.p>
    <x-mail.details>
        <x-mail.detail :label="__('Plan')">{{ $planName }}</x-mail.detail>
        <x-mail.detail :label="__('Username')" :last="true">{{ $username }}</x-mail.detail>
    </x-mail.details>
    <x-mail.button :href="$loginUrl">{{ __('Open the sign-in page') }}</x-mail.button>
</x-mail-frame>
