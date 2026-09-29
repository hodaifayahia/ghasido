{{-- The sender name and business email from Website Management (user request 2026-09-26); $businessSender comes from AppServiceProvider. --}}
<p style="margin: 0;">{{ $businessSender['name'] }}</p>
@if (($withContact ?? false) && $businessSender['email'])
    <p style="margin: 8px 0 0; font-size: 13px; line-height: 1.5;">
        {{ __('Questions? Write to us at') }}
        <a href="mailto:{{ $businessSender['email'] }}">{{ $businessSender['email'] }}</a>
    </p>
@endif
