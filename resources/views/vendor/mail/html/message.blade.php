@props(['title' => null, 'heading' => null, 'eyebrow' => null, 'preheader' => null, 'signoff' => true])
<x-mail::layout :title="$title" :heading="$heading" :eyebrow="$eyebrow" :preheader="$preheader" :signoff="$signoff">
{{ $slot }}
@isset($subcopy)
<x-slot:subcopy>
{{ $subcopy }}
</x-slot:subcopy>
@endisset
</x-mail::layout>
