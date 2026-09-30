@props(['url', 'color' => 'primary', 'align' => 'center'])
<x-mail.button :href="$url" :fallback="false">{!! $slot !!}</x-mail.button>
