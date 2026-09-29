<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('New GHASIDO contact message') }}</title>
</head>
<body style="margin: 0; padding: 24px; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 1.6;">
    <div style="max-width: 560px; margin: 0 auto;">
        <p style="margin: 0 0 16px;">{{ __('A visitor sent a message from the Contact Us page.') }}</p>

        <p style="margin: 0 0 4px;"><strong>{{ __('Name') }}:</strong> {{ $contact->name }}</p>
        <p style="margin: 0 0 4px;"><strong>{{ __('Email') }}:</strong> {{ $contact->email }}</p>
        @if ($contact->phone)
            <p style="margin: 0 0 4px;"><strong>{{ __('Phone') }}:</strong> {{ $contact->phone }}</p>
        @endif
        @if ($contact->organisation)
            <p style="margin: 0 0 4px;"><strong>{{ __('Hotel or organisation') }}:</strong> {{ $contact->organisation }}</p>
        @endif
        @if ($contact->employees)
            <p style="margin: 0 0 4px;"><strong>{{ __('Team size') }}:</strong> {{ $contact->employees }}</p>
        @endif

        <p style="margin: 16px 0; white-space: pre-line;">{{ $contact->message }}</p>

        @include('mail.partials.signature')
    </div>
</body>
</html>
