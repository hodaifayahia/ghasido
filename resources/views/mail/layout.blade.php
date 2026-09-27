{{-- Shared frame for the account and payment emails (client request
     2026-09-27). The language is the recipient's, set on the mailable. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin: 0; padding: 24px; background: #f4f9fe; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 1.6; color: #12233d;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e4edf8; border-radius: 14px; padding: 28px; text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }};">
        <p style="margin: 0 0 20px; font-weight: bold; font-size: 20px; color: #0b5cff;">GHASIDO</p>
        {{ $slot }}
        <p style="margin: 28px 0 0; color: #64748b; font-size: 14px;">{{ __('The GHASIDO team') }}</p>
    </div>
</body>
</html>
