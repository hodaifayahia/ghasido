<x-mail-frame
    :title="__('New GHASIDO contact message')"
    :eyebrow="__('Contact Us')"
    :preheader="\Illuminate\Support\Str::limit($contact->name.': '.$contact->message, 120)"
    :signoff="false"
>
    <x-mail.p>{{ __('A visitor sent a message from the Contact Us page.') }}</x-mail.p>
    <x-mail.details>
        <x-mail.detail :label="__('Name')">{{ $contact->name }}</x-mail.detail>
        <x-mail.detail :label="__('Email')" :last="! $contact->phone && ! $contact->organisation && ! $contact->employees"><a href="mailto:{{ $contact->email }}" style="color: #0b5cff; text-decoration: none;">{{ $contact->email }}</a></x-mail.detail>
        @if ($contact->phone)
            <x-mail.detail :label="__('Phone')" :last="! $contact->organisation && ! $contact->employees">{{ $contact->phone }}</x-mail.detail>
        @endif
        @if ($contact->organisation)
            <x-mail.detail :label="__('Hotel or organisation')" :last="! $contact->employees">{{ $contact->organisation }}</x-mail.detail>
        @endif
        @if ($contact->employees)
            <x-mail.detail :label="__('Team size')" :last="true">{{ $contact->employees }}</x-mail.detail>
        @endif
    </x-mail.details>
    <x-mail.p><strong>{{ __('Message') }}</strong></x-mail.p>
    <x-mail.note dir="auto">{!! nl2br(e($contact->message)) !!}</x-mail.note>
    <x-mail.button :href="'mailto:'.$contact->email" :fallback="false">{{ __('Reply to :name', ['name' => $contact->name]) }}</x-mail.button>
    <x-mail.p :muted="true" :last="true">{{ __('Replying to this email answers the visitor directly.') }}</x-mail.p>
</x-mail-frame>
