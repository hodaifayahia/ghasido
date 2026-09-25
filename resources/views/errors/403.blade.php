{{--
    The refusal a blocked user sees (spec 0001, AC-8).

    Built from the @theme tokens in resources/css/app.css only, and kept
    deliberately small: there is no client mockup for an error screen, so this
    invents as little layout as it can (AGENTS.md §0.2 rule 8 — a mockup for
    the error and empty states is an open question with the client).

    This is a plain Blade response, not an Inertia one. An Inertia <Link> visit
    to a guarded route therefore hard reloads to this URL to recover, which is
    Inertia working as designed. The status is still 403.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Access denied') }} - {{ config('app.name', 'GHASIDO') }}</title>

        <style>
            html { background-color: #f4f9fe; }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
        <link rel="icon" href="/favicon-192x192.png" type="image/png" sizes="192x192">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body class="bg-app font-sans antialiased">
        <main class="flex min-h-svh flex-col items-center justify-center gap-6 px-6 py-12 text-center">
            <img
                src="/brand/ghasido-logo.png"
                alt="{{ config('app.name', 'GHASIDO') }}"
                width="260"
                height="120"
                class="h-auto w-44"
            >

            <div class="flex max-w-md flex-col gap-2">
                <p class="font-heading text-brand-700 text-sm font-semibold tracking-[0.12em] uppercase">
                    {{ __('Error 403') }}
                </p>

                <h1 class="font-heading text-ink-royal text-[28px] leading-10 font-bold tracking-[-0.02em]">
                    {{ __('Access denied') }}
                </h1>

                <p class="text-ink-slate text-base leading-7">
                    {{ $exception?->getMessage() ?: __('You do not have access to this page.') }}
                </p>
            </div>

            <a
                href="{{ url('/dashboard') }}"
                class="bg-brand-600 shadow-btn hover:bg-brand-700 focus-visible:ring-brand-600/15 font-heading inline-flex h-11 items-center rounded-md px-5 text-sm font-semibold text-white transition-colors duration-150 focus-visible:ring-3 focus-visible:outline-none active:scale-[.97]"
            >
                {{ __('Back to your dashboard') }}
            </a>
        </main>
    </body>
</html>
