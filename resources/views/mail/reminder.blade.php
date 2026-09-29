<x-mail-frame
    :title="$subject"
    :eyebrow="__('Training reminder')"
    :preheader="\Illuminate\Support\Str::limit($body, 120)"
>
    <x-mail.p dir="auto">{!! \App\Support\MailText::paragraph($body) !!}</x-mail.p>
    <x-mail.button :href="$trainingUrl">{{ __('Continue your training') }}</x-mail.button>
    <x-slot:subcopy>
        {{ __('You receive training reminders because you agreed to them. You can change this at any time from your profile.') }}
        <a href="{{ $preferencesUrl }}" target="_blank" style="color: #0b5cff; text-decoration: underline;">{{ __('Manage email reminders') }}</a>
    </x-slot:subcopy>
</x-mail-frame>
