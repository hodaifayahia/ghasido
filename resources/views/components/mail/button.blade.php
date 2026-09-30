{{--
    Bulletproof call-to-action button: a VML round rectangle for Outlook on
    Windows, a padded link everywhere else. brand-600 #0b5cff (hex: email).
    No blank lines inside, so Markdown mails keep it as one HTML block.
--}}
@props(['href', 'fallback' => true])
@php($label = trim((string) $slot))
@php($align = app()->getLocale() === 'ar' ? 'right' : 'left')
<table role="presentation" class="g-btn" border="0" cellspacing="0" cellpadding="0" align="{{ $align }}" style="margin: 8px 0 24px;">
<tr>
<td align="center" bgcolor="#0b5cff" style="border-radius: 10px; background-color: #0b5cff;">
<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $href }}" style="height:50px;v-text-anchor:middle;width:280px;" arcsize="20%" stroke="f" fillcolor="#0b5cff"><w:anchorlock/><center style="color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">{{ $label }}</center></v:roundrect><![endif]-->
<!--[if !mso]><!--><a href="{{ $href }}" target="_blank" style="display: inline-block; padding: 15px 30px; font-size: 16px; line-height: 20px; font-weight: 700; color: #ffffff; text-decoration: none; text-align: center; background-color: #0b5cff; border: 1px solid #0b5cff; border-radius: 10px; mso-hide: all;">{{ $label }}&nbsp;&nbsp;{{ app()->getLocale() === 'ar' ? '←' : '→' }}</a><!--<![endif]-->
</td>
</tr>
</table>
<div style="clear: both; height: 0; line-height: 0; font-size: 0;">&nbsp;</div>
@if ($fallback)
<p style="margin: 0 0 16px; font-size: 13px; line-height: 20px; color: #64748b; word-break: break-all;">{{ __('If the button does not work, copy this link into your browser:') }}<br><a href="{{ $href }}" target="_blank" style="color: #0b5cff; text-decoration: underline;">{{ $href }}</a></p>
@endif
