{{--
    A callout box. Tones copy the @theme tint/text pairs (hex: email):
    info brand-50 #eff5ff / brand-800 #0f3a9e, danger #fdecec / #b42318,
    warning #fef3e2 / #8a5a06, success #e7f8f0 / #1b7f4f.
--}}
@props(['tone' => 'info', 'dir' => null])
@php
    $tones = [
        'info' => ['#eff5ff', '#0f3a9e', '#0b5cff'],
        'danger' => ['#fdecec', '#b42318', '#ef4444'],
        'warning' => ['#fef3e2', '#8a5a06', '#f5a623'],
        'success' => ['#e7f8f0', '#1b7f4f', '#2fbe7a'],
    ];
    [$bg, $fg, $edge] = $tones[$tone] ?? $tones['info'];
    $side = app()->getLocale() === 'ar' ? 'right' : 'left';
@endphp
<table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="width: 100%; border-collapse: separate; margin: 4px 0 20px;">
<tr>
<td @if ($dir) dir="{{ $dir }}" @endif style="padding: 14px 18px; background-color: {{ $bg }}; border-{{ $side }}: 4px solid {{ $edge }}; border-radius: 10px; font-size: 15px; line-height: 24px; color: {{ $fg }};{{ $dir ? " text-align: start;" : "" }}">{{ $slot }}</td>
</tr>
</table>
