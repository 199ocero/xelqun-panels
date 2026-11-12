@php($panel = app(\Xelqun\Panels\Panels::class)->current())
@php($switcher = $panel->getThemeSwitcher())
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-xq-panel
    data-xq-preset="{{ $panel->getPreset()->value }}"
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
                    @if ($switcher)
                        {{-- The style and colors someone picked from the user menu. useAppearance keeps them up to date afterwards. --}}
                        var root = document.documentElement;
                        var saved = @json($switcher['saved'] ?: null) || JSON.parse(localStorage.getItem('xq-panel-appearance') || '{}');
                        var palette = @json(array_column($switcher['palette'], null, 'name'));
                        var primary = palette[saved.primary] && ! palette[saved.primary].gray ? palette[saved.primary] : null;
                        var secondary = palette[saved.secondary] && palette[saved.secondary].gray ? palette[saved.secondary] : null;

                        if (@json($switcher['presets']).indexOf(saved.preset) !== -1) {
                            root.setAttribute('data-xq-preset', saved.preset);
                        }

                        if (primary) {
                            root.style.setProperty('--xq-color-primary', primary.hex);
                            root.style.setProperty('--xq-color-primary-fill', primary.fill);
                            root.style.setProperty('--xq-color-primary-foreground', primary.text);
                        }

                        if (secondary) {
                            root.style.setProperty('--xq-color-secondary', secondary.hex);
                            root.style.setProperty('--xq-color-gray', secondary.hex);
                        }
                    @endif
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
