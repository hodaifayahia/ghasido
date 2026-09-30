{{-- One row of <x-mail.details>: label on top, value below (works at any width and in RTL). --}}
@props(['label', 'last' => false])
<tr>
<td style="padding: 12px 0; {{ $last ? '' : 'border-bottom: 1px solid #e4edf8;' }}">
<p style="margin: 0 0 2px; font-size: 12px; line-height: 18px; font-weight: 600; color: #64748b;">{{ $label }}</p>
<p style="margin: 0; font-size: 16px; line-height: 24px; font-weight: 600; color: #12233d; word-break: break-word;">{{ $slot }}</p>
</td>
</tr>
