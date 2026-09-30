{!! __('A visitor sent a message from the Contact Us page.') !!}

{!! __('Name') !!}: {!! $contact->name !!}
{!! __('Email') !!}: {!! $contact->email !!}
@if ($contact->phone)
{!! __('Phone') !!}: {!! $contact->phone !!}
@endif
@if ($contact->organisation)
{!! __('Hotel or organisation') !!}: {!! $contact->organisation !!}
@endif
@if ($contact->employees)
{!! __('Team size') !!}: {!! $contact->employees !!}
@endif

{!! __('Message') !!}:
{!! $contact->message !!}

{!! __('Replying to this email answers the visitor directly.') !!}
@include('mail.text.footer', ['signoff' => false])
