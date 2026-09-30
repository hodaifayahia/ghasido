<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $direction ?? 'ltr' }}"  @class(['dark' => ($appearance ?? 'light') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "light" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        {{-- Paints the Guesvia page background before app.css loads, so there
             is no white flash. Keep in sync with --grad-page in app.css. --}}
        <style>
            html {
                background-color: #f4f9fe;
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico?v=ghasido-20260925" sizes="any">
        <link rel="icon" href="/favicon-32x32.png?v=ghasido-20260925" type="image/png" sizes="32x32">
        {{-- Google Search shows a site's favicon next to its result and wants
             a square of a multiple of 48px (48, 96, 192). --}}
        <link rel="icon" href="/favicon-48x48.png?v=ghasido-20260930" type="image/png" sizes="48x48">
        <link rel="icon" href="/favicon-96x96.png?v=ghasido-20260930" type="image/png" sizes="96x96">
        <link rel="icon" href="/favicon-192x192.png?v=ghasido-20260925" type="image/png" sizes="192x192">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=ghasido-20260925">
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#0b5cff">

        @if ($page['component'] === 'Welcome')
            {{-- Search engines: the site name and logo for the result card. --}}
            <link rel="canonical" href="{{ url('/') }}">
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="GHASIDO">
            <meta property="og:url" content="{{ url('/') }}">
            <meta property="og:image" content="{{ url('/brand/ghasido-app-512.png') }}">
            <script type="application/ld+json">
                {!! json_encode([
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        ['@type' => 'WebSite', 'name' => 'GHASIDO', 'url' => url('/')],
                        ['@type' => 'Organization', 'name' => 'GHASIDO', 'url' => url('/'), 'logo' => url('/brand/ghasido-app-512.png')],
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
            </script>
        @endif

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'GHASIDO') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
