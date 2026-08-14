<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            /* Matches --background in app.css, so the first paint before the
               stylesheet lands is already the right colour. */
            html {
                background-color: #fbfaf9;
            }

            html.dark {
                background-color: #08080a;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- The manifest carries one theme_color, but the app has two palettes.
             These paired metas are what actually tints the mobile browser
             chrome, so they track --background the same way the style above
             does, and change with it. --}}
        <meta name="theme-color" content="#fbfaf9" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#08080a" media="(prefers-color-scheme: dark)">

        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Prompt Queue') }}">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <link rel="manifest" href="{{ route('manifest') }}">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Prompt Queue') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
