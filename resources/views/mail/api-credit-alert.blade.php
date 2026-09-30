<x-mail-frame
    :title="$empty ? __('AI credit used up') : __('AI credit running low')"
    :eyebrow="__('AI credit')"
    :preheader="$empty
        ? __('The :service credit has run out, so the AI features that use it are paused. Lessons, tests and the phrasebook still work.', ['service' => $service])
        : __('The :service credit is running low (about 20% left). When it runs out, the AI features that use it pause for every learner.', ['service' => $service])"
>
    <x-mail.p>{{ __('Hello :name,', ['name' => $name]) }}</x-mail.p>
    <x-mail.note :tone="$empty ? 'danger' : 'warning'">
        @if ($empty)
            {{ __('The :service credit has run out, so the AI features that use it are paused. Lessons, tests and the phrasebook still work.', ['service' => $service]) }}
        @else
            {{ __('The :service credit is running low (about 20% left). When it runs out, the AI features that use it pause for every learner.', ['service' => $service]) }}
        @endif
    </x-mail.note>
    @if ($dollars || count($units) > 0)
        <x-mail.details>
            @if ($dollars)
                <x-mail.detail :label="__('Credit left')" :last="count($units) === 0">{{ $dollars }}</x-mail.detail>
            @endif
            @foreach ($units as $line)
                <x-mail.detail :label="__('Usage left')" :last="$loop->last">{{ $line }}</x-mail.detail>
            @endforeach
        </x-mail.details>
    @endif
    <x-mail.p>
        @if ($forOwner)
            {{ __('Recharge :provider on the owner console:', ['provider' => $provider]) }}
        @else
            {{ __('Please ask the platform owner to recharge it. You can follow the credit on your dashboard:') }}
        @endif
    </x-mail.p>
    <x-mail.button :href="$url">{{ $forOwner ? __('Open the owner console') : __('Open your dashboard') }}</x-mail.button>
</x-mail-frame>
