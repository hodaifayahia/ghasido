<x-mail-frame
    :title="__('Your GHASIDO hotel account is ready')"
    :eyebrow="__('Welcome to GHASIDO')"
    :preheader="__('Your hotel request for :hotel has been approved. Your manager account is now active and you can sign in to GHASIDO.', ['hotel' => $hotelName])"
>
    <x-mail.p>{{ __('Hello :name,', ['name' => $managerName]) }}</x-mail.p>
    <x-mail.p>{{ __('Your hotel request for :hotel has been approved. Your manager account is now active and you can sign in to GHASIDO.', ['hotel' => $hotelName]) }}</x-mail.p>
    <x-mail.details>
        <x-mail.detail :label="__('Hotel')">{{ $hotelName }}</x-mail.detail>
        <x-mail.detail :label="__('Username')" :last="true">{{ $username }}</x-mail.detail>
    </x-mail.details>
    <x-mail.button :href="$loginUrl">{{ __('Open the sign-in page') }}</x-mail.button>
</x-mail-frame>
