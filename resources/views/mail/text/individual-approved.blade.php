{!! __('Hello :name,', ['name' => $name]) !!}

{!! __('We have confirmed your payment. Your :plan subscription is now active and you can sign in to start learning.', ['plan' => $planName]) !!}

{!! __('Plan') !!}: {!! $planName !!}
{!! __('Username') !!}: {!! $username !!}

{!! __('Open the sign-in page') !!}: {!! $loginUrl !!}
@include('mail.text.footer')
