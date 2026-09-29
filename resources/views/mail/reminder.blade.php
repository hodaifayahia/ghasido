<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin: 0; padding: 24px; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 1.6;">
    <div style="max-width: 560px; margin: 0 auto;">
        <p style="margin: 0 0 24px;">{!! nl2br(e($body)) !!}</p>

        <div style="margin: 0 0 24px;">
            @include('mail.partials.signature', ['withContact' => true])
        </div>

        <p style="margin: 0; font-size: 13px; line-height: 1.5;">
            {{ __('You receive training reminders because you agreed to them. You can change this at any time from your profile.') }}
        </p>
    </div>
</body>
</html>
