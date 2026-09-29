{{--
    The GHASIDO email layout (client request 2026-09-29), used by every
    mailable (through <x-mail-frame>) and by Laravel's own notification mails
    (resources/views/vendor/mail/html/layout.blade.php).

    Email clients ignore Tailwind, CSS variables and most <style> rules, so
    this file is the one place in the repo that writes colours as hex, inline
    and table-based. Every value is a copy of an @theme token in
    resources/css/app.css: brand-600 #0b5cff, brand-700 #1249c9, brand-50
    #eff5ff, brand-100 #dbe8ff, ink #12233d, ink-muted #64748b, ink-faint
    #94a3b8, line #e4edf8, app #f4f9fe, ink-royal #110da8.

    600px wide, bulletproof in Outlook (MSO tables), readable on phones
    (media query) and RTL with an Arabic font stack when the mail is sent in
    Arabic (the recipient's locale, set on the mailable).

    Variables: $title, $slot; optional $preheader, $heading ('' hides it,
    null uses the title),
    $eyebrow, $subcopy, $signoff (bool).
--}}
@php
    $rtl = app()->getLocale() === 'ar';
    $dir = $rtl ? 'rtl' : 'ltr';
    $align = $rtl ? 'right' : 'left';
    $font = $rtl
        ? "'Cairo', 'Segoe UI', Tahoma, 'Geeza Pro', Arial, sans-serif"
        : "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
    $headingFont = $rtl
        ? $font
        : "'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
    $appUrl = rtrim((string) config('app.url'), '/');
    $siteHost = parse_url($appUrl, PHP_URL_HOST) ?: $appUrl;
    $contact = app(\App\Services\Mail\MailSettings::class)->contactAddress();
    $heading = ($heading ?? null) === null ? $title : $heading;
    $preheader = $preheader ?? null;
    $eyebrow = $eyebrow ?? null;
    $subcopy = $subcopy ?? null;
    $signoff = $signoff ?? true;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{{ $title }}</title>
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
<style>table, td, h1, p, a { font-family: Arial, sans-serif !important; }</style>
<![endif]-->
<style>
    :root { color-scheme: light; supported-color-schemes: light; }
    body { margin: 0 !important; padding: 0 !important; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-collapse: collapse; }
    img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; display: block; }
    a { color: #0b5cff; }
    a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; }
    u + #body a { color: inherit; text-decoration: none; }
    @media only screen and (max-width: 620px) {
        .g-outer { padding: 20px 12px !important; }
        .g-pad { padding-left: 24px !important; padding-right: 24px !important; }
        .g-h1 { font-size: 22px !important; line-height: 30px !important; }
        .g-logo { width: 176px !important; height: auto !important; }
        .g-btn a { display: block !important; }
        .g-stack { display: block !important; width: 100% !important; }
    }
</style>
</head>
<body id="body" dir="{{ $dir }}" style="margin: 0; padding: 0; width: 100%; background-color: #f4f9fe; word-spacing: normal;">
@if ($preheader)
<div style="display: none; max-height: 0; max-width: 0; overflow: hidden; opacity: 0; mso-hide: all; font-size: 1px; line-height: 1px; color: #f4f9fe;">{{ $preheader }}&#8203;@for ($i = 0; $i < 40; $i++)&nbsp;&zwnj;@endfor</div>
@endif
<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#f4f9fe" style="width: 100%; background-color: #f4f9fe;">
<tr>
<td class="g-outer" align="center" style="padding: 36px 16px;">
<!--[if mso]><table role="presentation" width="600" border="0" cellspacing="0" cellpadding="0" align="center"><tr><td><![endif]-->
<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="width: 100%; max-width: 600px; margin: 0 auto;">
    {{-- Card --}}
    <tr>
        <td style="background-color: #ffffff; border: 1px solid #e4edf8; border-radius: 16px; box-shadow: 0 4px 16px rgba(11, 92, 255, 0.06);">
            <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="width: 100%;">
                <tr>
                    <td height="6" style="height: 6px; line-height: 6px; font-size: 6px; background-color: #0b5cff; background-image: linear-gradient(90deg, #0b5cff 0%, #1249c9 100%); border-radius: 16px 16px 0 0;">&nbsp;</td>
                </tr>
                <tr>
                    <td class="g-pad" align="center" style="padding: 30px 48px 6px;">
                        <a href="{{ $appUrl }}" target="_blank" style="text-decoration: none; display: inline-block;">
                            <img class="g-logo" src="{{ $appUrl }}/brand/email/ghasido-logo.png" width="210" height="62" alt="GHASIDO — English for Hotel Staff" style="width: 210px; max-width: 100%; height: auto; border: 0; display: block; margin: 0 auto; font-family: {!! $headingFont !!}; font-size: 24px; font-weight: 700; color: #0b5cff;">
                        </a>
                    </td>
                </tr>
                <tr>
                    <td class="g-pad" style="padding: 18px 48px 0;">
                        <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td height="1" style="height: 1px; line-height: 1px; font-size: 1px; background-color: #e4edf8;">&nbsp;</td></tr></table>
                    </td>
                </tr>
                @if ($eyebrow || $heading)
                    <tr>
                        <td class="g-pad" dir="{{ $dir }}" align="{{ $align }}" style="padding: 28px 48px 0; text-align: {{ $align }};">
                            @if ($eyebrow)
                                <p style="margin: 0 0 10px; font-family: {!! $font !!}; font-size: 13px; line-height: 18px; font-weight: 700; color: #0b5cff; {{ $rtl ? '' : 'letter-spacing: 0.06em; text-transform: uppercase;' }}">{{ $eyebrow }}</p>
                            @endif
                            @if ($heading)
                                <h1 class="g-h1" style="margin: 0; font-family: {!! $headingFont !!}; font-size: 26px; line-height: 34px; font-weight: 700; color: #110da8; {{ $rtl ? '' : 'letter-spacing: -0.02em;' }}">{{ $heading }}</h1>
                            @endif
                        </td>
                    </tr>
                @endif
                <tr>
                    <td class="g-pad" dir="{{ $dir }}" align="{{ $align }}" style="padding: 20px 48px 8px; text-align: {{ $align }}; font-family: {!! $font !!}; font-size: 16px; line-height: 26px; color: #12233d;">
                        {{ $slot }}
                    </td>
                </tr>
                @if ($signoff)
                    <tr>
                        <td class="g-pad" dir="{{ $dir }}" align="{{ $align }}" style="padding: 18px 48px 34px; text-align: {{ $align }}; font-family: {!! $font !!}; font-size: 15px; line-height: 24px; color: #64748b;">
                            {{ __('Warm regards,') }}<br>
                            <strong style="color: #1249c9; font-weight: 700;">{{ __('The GHASIDO team') }}</strong>
                        </td>
                    </tr>
                @else
                    <tr><td height="28" style="height: 28px; line-height: 28px; font-size: 1px;">&nbsp;</td></tr>
                @endif
                @if ($subcopy)
                    <tr>
                        <td class="g-pad" dir="{{ $dir }}" align="{{ $align }}" style="padding: 18px 48px 26px; border-top: 1px solid #e4edf8; text-align: {{ $align }}; font-family: {!! $font !!}; font-size: 13px; line-height: 20px; color: #64748b; word-break: break-word;">
                            {{ $subcopy }}
                        </td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
    {{-- Footer --}}
    <tr>
        <td align="center" dir="{{ $dir }}" style="padding: 30px 24px 8px; text-align: center; font-family: {!! $font !!};">
            <img src="{{ $appUrl }}/brand/email/palm-island-tagline.png" width="100" height="76" alt="{{ __('Hotel People. Brighter Futures.') }}" style="width: 100px; height: auto; border: 0; display: block; margin: 0 auto 14px; font-size: 13px; color: #1249c9;">
            <p style="margin: 0 0 6px; font-size: 14px; line-height: 22px; color: #1249c9; font-weight: 700;">{{ __('English for hotel teams, one real situation at a time.') }}</p>
            <p style="margin: 0 0 14px; font-size: 13px; line-height: 21px; color: #64748b;">
                <a href="mailto:{{ $contact }}" style="color: #0b5cff; text-decoration: none; font-weight: 600;">{{ $contact }}</a>
                &nbsp;&middot;&nbsp;
                <a href="{{ $appUrl }}" target="_blank" style="color: #0b5cff; text-decoration: none; font-weight: 600;">{{ $siteHost }}</a>
            </p>
            <p style="margin: 0; font-size: 12px; line-height: 18px; color: #94a3b8;">&copy; {{ date('Y') }} GHASIDO. {{ __('All rights reserved.') }}</p>
        </td>
    </tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td>
</tr>
</table>
</body>
</html>
