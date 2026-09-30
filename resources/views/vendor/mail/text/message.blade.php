@props(['title' => null, 'heading' => null, 'eyebrow' => null, 'preheader' => null, 'signoff' => true])
@if (! empty($heading))
{{ $heading }}

@endif
{!! strip_tags((string) $slot) !!}
@isset($subcopy)

{!! strip_tags((string) $subcopy) !!}
@endisset
@include('mail.text.footer', ['signoff' => $signoff])
