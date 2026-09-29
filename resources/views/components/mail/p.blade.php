{{-- A paragraph of the GHASIDO email body (inline styles: email clients ignore classes). --}}
@props(['muted' => false, 'last' => false, 'dir' => null])
<p @if ($dir) dir="{{ $dir }}" @endif style="margin: 0 0 {{ $last ? '0' : '16px' }}; font-size: {{ $muted ? '14px' : '16px' }}; line-height: {{ $muted ? '22px' : '26px' }}; color: {{ $muted ? '#64748b' : '#12233d' }};{{ $dir ? ' text-align: start;' : '' }}">{{ $slot }}</p>
