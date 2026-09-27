<x-mail-frame :title="$subjectLine">
    <p style="margin: 0 0 16px;">{{ __('Hello :name,', ['name' => $name]) }}</p>
    <div style="margin: 0; white-space: pre-line;">{{ $body }}</div>
</x-mail-frame>
