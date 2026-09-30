{!! __('Hello :name,', ['name' => $managerName]) !!}

{!! __('Your hotel request for :hotel has been approved. Your manager account is now active and you can sign in to GHASIDO.', ['hotel' => $hotelName]) !!}

{!! __('Hotel') !!}: {!! $hotelName !!}
{!! __('Username') !!}: {!! $username !!}

{!! __('Open the sign-in page') !!}: {!! $loginUrl !!}
@include('mail.text.footer')
