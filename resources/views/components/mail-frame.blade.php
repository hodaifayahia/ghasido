{{-- The branded frame of every GHASIDO email; see mail/layout.blade.php. --}}
@props(['title', 'preheader' => null, 'heading' => null, 'eyebrow' => null, 'signoff' => true])
@include('mail.layout', [
    'title' => $title,
    'preheader' => $preheader,
    'heading' => $heading,
    'eyebrow' => $eyebrow,
    'signoff' => $signoff,
    'subcopy' => $subcopy ?? null,
    'slot' => $slot,
])
