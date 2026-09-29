{!! __('Hello :name,', ['name' => $name]) !!}

{!! __('We could not confirm the payment sent for :account, so the subscription has not been activated.', ['account' => $accountName]) !!}

{!! __('Reason') !!}: {!! $reason !!}

{!! __('Please reply to this email or contact us to send a valid payment, and we will activate your account.') !!}
@include('mail.text.footer')
