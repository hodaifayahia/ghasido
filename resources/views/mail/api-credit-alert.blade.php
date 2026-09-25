<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $empty ? __('AI credit used up') : __('AI credit running low') }}</title>
</head>
<body style="margin: 0; padding: 24px; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 1.6;">
    <div style="max-width: 560px; margin: 0 auto;">
        <p style="margin: 0 0 16px;">{{ __('Hello :name,', ['name' => $name]) }}</p>

        <p style="margin: 0 0 16px;">
            @if ($empty)
                {{ __('The :service credit has run out, so the AI features that use it are paused. Lessons, tests and the phrasebook still work.', ['service' => $service]) }}
            @else
                {{ __('The :service credit is running low (about 20% left). When it runs out, the AI features that use it pause for every learner.', ['service' => $service]) }}
            @endif
        </p>

        @if ($dollars || count($units) > 0)
            <p style="margin: 0 0 16px;">
                @if ($dollars)
                    <strong>{{ $dollars }}</strong><br>
                @endif
                @foreach ($units as $line)
                    {{ $line }}<br>
                @endforeach
            </p>
        @endif

        <p style="margin: 0 0 24px;">
            @if ($forOwner)
                {{ __('Recharge :provider on the owner console:', ['provider' => $provider]) }}
            @else
                {{ __('Please ask the platform owner to recharge it. You can follow the credit on your dashboard:') }}
            @endif
            <br>
            <a href="{{ $url }}">{{ $url }}</a>
        </p>

        <p style="margin: 0;">{{ config('app.name') }}</p>
    </div>
</body>
</html>
