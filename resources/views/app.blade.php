@php($panel = app(\Xelqun\Panels\Panels::class)->current())
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-xq-panel
    style="--xq-panel-font: '{{ $panel->getFont() }}'; @foreach ($panel->getThemeVariables() as $variable => $color) {{ $variable }}: {{ $color }}; @endforeach"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @if ($panel->getFavicon())
            <link rel="icon" href="{{ $panel->getFavicon() }}">
        @endif

        {{-- Before paint, so a dark theme never flashes white. XqPanelLayout keeps this up to date afterwards. --}}
        <script>
            (function () {
                try {
                    var theme = @json($panel->getForcedThemeMode()) || localStorage.getItem('xq-panel-theme') || @json($panel->getDefaultThemeMode());
                    var dark = theme === 'dark' || (theme !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);

                    var motion = localStorage.getItem('xq-panel-motion');
                    document.documentElement.toggleAttribute('data-xq-motion-off', motion ? motion === 'off' : ! @json($panel->hasAnimations()));
                } catch (error) {}
            })();
        </script>

        {{-- Only after the page has loaded, so a slow font host can never hold it back. --}}
        @php($fontUrl = 'https://fonts.bunny.net/css?family='.\Illuminate\Support\Str::slug($panel->getFont()).':400,500,600,700&display=swap')
        <script>
            window.addEventListener('load', function () {
                var font = document.createElement('link');
                font.rel = 'stylesheet';
                font.href = @json($fontUrl);
                document.head.appendChild(font);
            });
        </script>
        <noscript><link rel="stylesheet" href="{{ $fontUrl }}"></noscript>

        @vite(config('xelqun-panels.vite'))
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="antialiased">
        <x-inertia::app />
    </body>
</html>
