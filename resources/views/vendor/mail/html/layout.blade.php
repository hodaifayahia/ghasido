{{--
    Laravel's notification mails (password reset…) in the GHASIDO layout
    (client request 2026-09-29): the Markdown body is parsed here and handed
    to resources/views/mail/layout.blade.php, the same frame as every
    mailable.
--}}
@props(['title' => null, 'heading' => null, 'eyebrow' => null, 'preheader' => null, 'signoff' => true])
@include('mail.layout', [
    'title' => $title ?? config('app.name'),
    'heading' => $heading ?? '',
    'eyebrow' => $eyebrow ?? null,
    'preheader' => $preheader ?? null,
    'signoff' => $signoff ?? true,
    'slot' => Illuminate\Mail\Markdown::parse((string) $slot),
    'subcopy' => isset($subcopy) && trim((string) $subcopy) !== '' ? Illuminate\Mail\Markdown::parse((string) $subcopy) : null,
])
