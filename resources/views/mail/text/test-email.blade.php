{!! __('This test email was sent from the Email settings page. If you can read it, emails are being delivered.') !!}

{!! __('Sent through') !!}: {!! $server !!}
{!! __('From') !!}: {!! $from !!}
{!! __('Sent at') !!}: {!! $sentAt !!}

{!! __('Tip: check that this email reached the inbox, not the spam folder. If it went to spam, see the DNS steps in the email setup guide.') !!}
@include('mail.text.footer')
