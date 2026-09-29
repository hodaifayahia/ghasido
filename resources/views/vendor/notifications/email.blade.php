{{--
    Laravel's notification mail (password reset…) in the GHASIDO layout
    (client request 2026-09-29). The greeting becomes the heading; the
    layout signs off, so no "Regards," here unless a notification sets one.
--}}
@php
    $heading = ! empty($greeting) ? $greeting : ($level === 'error' ? __('Whoops!') : __('Hello!'));
@endphp
<x-mail::message :heading="$heading" :preheader="$introLines[0] ?? null">
@foreach ($introLines as $line)
{{ $line }}

@endforeach
@isset($actionText)
<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

@endisset
@foreach ($outroLines as $line)
{{ $line }}

@endforeach
@if (! empty($salutation))
{{ $salutation }}
@endif
@isset($actionText)
<x-slot:subcopy>
{{ __('If the “:actionText” button does not work, copy this link into your browser:', ['actionText' => $actionText]) }} <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
