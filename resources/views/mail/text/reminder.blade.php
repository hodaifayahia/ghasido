{!! trim($body) !!}

{!! __('Continue your training') !!}: {!! $trainingUrl !!}
@include('mail.text.footer')

{!! __('You receive training reminders because you agreed to them. You can change this at any time from your profile.') !!}
{!! __('Manage email reminders') !!}: {!! $preferencesUrl !!}
