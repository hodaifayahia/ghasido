<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Your GHASIDO hotel account is ready') }}</title>
</head>
<body style="margin: 0; padding: 24px; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 1.6;">
    <div style="max-width: 560px; margin: 0 auto;">
        <p style="margin: 0 0 16px;">{{ __('Hello :name,', ['name' => $managerName]) }}</p>

        <p style="margin: 0 0 16px;">
            {{ __('Your hotel request for :hotel has been approved. Your manager account is now active and you can sign in to GHASIDO.', ['hotel' => $hotelName]) }}
        </p>

        <p style="margin: 0 0 16px;">
            {{ __('Username: :username', ['username' => $username]) }}
        </p>

        <p style="margin: 0 0 24px;">
            <a href="{{ $loginUrl }}">{{ __('Open the sign-in page') }}</a>
        </p>

        <p style="margin: 0;">{{ config('app.name') }}</p>
    </div>
</body>
</html>