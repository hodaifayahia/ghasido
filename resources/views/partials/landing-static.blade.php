@php
    $section = fn (string $key): array => (array) ($content[$key] ?? []);
    $items = fn (string $key): array => array_values(array_filter((array) ($content[$key]['items'] ?? []), 'is_array'));
    $steps = array_values(array_filter((array) ($content['journey']['steps'] ?? []), 'is_array'));
@endphp
<div id="seo-fallback" class="mx-auto max-w-5xl px-6 py-10 text-ink">
    <header class="flex items-center gap-3">
        <img src="/brand/ghasido-app-192.png" alt="GHASIDO" width="48" height="48">
        <p class="font-heading text-2xl font-bold text-brand-700">GHASIDO</p>
        <nav class="ms-auto flex gap-4 text-sm font-semibold text-brand-700">
            <a href="/contact">{{ $section('navigation')['contact'] ?? 'Contact us' }}</a>
            <a href="/login">{{ $section('navigation')['login'] ?? 'Log in' }}</a>
            <a href="/hotel-signup">{{ $section('navigation')['get_started'] ?? 'Get started' }}</a>
        </nav>
    </header>

    <main class="mt-10 grid gap-10">
        <section>
            <p class="text-sm font-semibold uppercase text-brand-600">{{ $section('hero')['eyebrow'] ?? '' }}</p>
            <h1 class="font-heading mt-2 text-4xl font-bold text-ink-royal">GHASIDO: {{ $section('hero')['title'] ?? 'Hotel English your team can use on their next shift.' }}</h1>
            <p class="mt-4 text-lg text-ink-slate">{{ $section('hero')['description'] ?? '' }}</p>
        </section>

        @foreach (['why_us', 'features', 'ai', 'roles'] as $key)
            @if (! empty($section($key)['title']))
                <section>
                    <h2 class="font-heading text-2xl font-semibold text-brand-900">{{ $section($key)['title'] }}</h2>
                    <p class="mt-2 text-ink-slate">{{ $section($key)['description'] ?? '' }}</p>
                    @if ($items($key) !== [])
                        <ul class="mt-3 grid gap-2">
                            @foreach ($items($key) as $item)
                                <li><strong>{{ $item['title'] ?? '' }}</strong> — {{ $item['description'] ?? '' }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        @endforeach

        @if ($steps !== [])
            <section>
                <h2 class="font-heading text-2xl font-semibold text-brand-900">{{ $section('journey')['title'] ?? '' }}</h2>
                <ol class="mt-3 grid gap-2">
                    @foreach ($steps as $step)
                        <li><strong>{{ $step['title'] ?? '' }}</strong> — {{ $step['description'] ?? '' }}</li>
                    @endforeach
                </ol>
            </section>
        @endif

        @if (! empty($section('about')['title']))
            <section>
                <h2 class="font-heading text-2xl font-semibold text-brand-900">{{ $section('about')['title'] }}</h2>
                <p class="mt-2 text-ink-slate">{{ $section('about')['description'] ?? '' }}</p>
            </section>
        @endif
    </main>

    <footer class="mt-10 text-sm text-ink-slate">
        <p>GHASIDO — {{ $section('footer')['tagline'] ?? 'English training made for hotel teams.' }}</p>
    </footer>
</div>
