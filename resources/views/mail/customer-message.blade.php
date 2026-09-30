<x-mail-frame :title="$subjectLine" :preheader="\Illuminate\Support\Str::limit($body, 120)">
    <x-mail.p>{{ __('Hello :name,', ['name' => $name]) }}</x-mail.p>
    <x-mail.p :last="true" dir="auto">{!! \App\Support\MailText::paragraph($body) !!}</x-mail.p>
</x-mail-frame>
