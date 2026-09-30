{!! __(':customer sent a payment for the :plan plan (:amount).', ['customer' => $customer, 'plan' => $planName, 'amount' => $amount]) !!}

{!! __('Customer') !!}: {!! $customer !!}
{!! __('Plan') !!}: {!! $planName !!}
{!! __('Amount') !!}: {!! $amount !!}
{!! __('Method') !!}: {!! $method !!}
@if ($reference)
{!! __('Reference') !!}: {!! $reference !!}
@endif

{!! __('Review the payment') !!}: {!! $reviewUrl !!}
@include('mail.text.footer', ['signoff' => false])
