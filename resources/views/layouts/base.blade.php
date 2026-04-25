<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php($branding = rescue(fn () => \App\Models\Setting::getValue('app.branding', []), []))
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="pharmanp-base-path" content="{{ request()->getBaseUrl() }}">
    <meta name="pharmanp-accent-color" content="{{ $branding['accent_color'] ?? '#0f766e' }}">
    <title>{{ $branding['app_name'] ?? config('app.name', 'PharmaNP') }}</title>
    @if (! empty($branding['favicon_url']))
        <link rel="icon" href="{{ $branding['favicon_url'] }}">
    @endif
    <style>
        :root {
            --brand-accent: {{ $branding['accent_color'] ?? '#0f766e' }};
        }
    </style>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body>
    @yield('body')
</body>
</html>
