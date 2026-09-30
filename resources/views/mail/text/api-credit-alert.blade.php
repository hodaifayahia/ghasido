{!! __('Hello :name,', ['name' => $name]) !!}

@if ($empty)
{!! __('The :service credit has run out, so the AI features that use it are paused. Lessons, tests and the phrasebook still work.', ['service' => $service]) !!}
@else
{!! __('The :service credit is running low (about 20% left). When it runs out, the AI features that use it pause for every learner.', ['service' => $service]) !!}
@endif

@if ($dollars)
{!! __('Credit left') !!}: {!! $dollars !!}
@endif
@foreach ($units as $line)
- {!! $line !!}
@endforeach

@if ($forOwner)
{!! __('Recharge :provider on the owner console:', ['provider' => $provider]) !!}
@else
{!! __('Please ask the platform owner to recharge it. You can follow the credit on your dashboard:') !!}
@endif
{!! $url !!}
@include('mail.text.footer')
