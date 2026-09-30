{{-- Plain-text footer of every GHASIDO email (the text/plain part). --}}
@php($contact = app(\App\Services\Mail\MailSettings::class)->contactAddress())
@if ($signoff ?? true)

{!! __('Warm regards,') !!}
{!! __('The GHASIDO team') !!}
@endif

--
GHASIDO · {!! __('English for hotel teams, one real situation at a time.') !!}
{!! $contact !!} · {!! rtrim((string) config('app.url'), '/') !!}
© {{ date('Y') }} GHASIDO. {!! __('All rights reserved.') !!}
