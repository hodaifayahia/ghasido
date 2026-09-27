<x-mail-frame :title="__('Your GHASIDO subscription is active')">
    <p style="margin: 0 0 16px;">{{ __('Hello :name,', ['name' => $name]) }}</p>
    <p style="margin: 0 0 16px;">{{ __('We have confirmed your payment. Your :plan subscription is now active and you can sign in to start learning.', ['plan' => $planName]) }}</p>
    <p style="margin: 0 0 16px;">{{ __('Username: :username', ['username' => $username]) }}</p>
    <p style="margin: 0;"><a href="{{ $loginUrl }}" style="display: inline-block; background: #0b5cff; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 10px; font-weight: bold;">{{ __('Open the sign-in page') }}</a></p>
</x-mail-frame>
