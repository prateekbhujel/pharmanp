<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php($branding = rescue(fn () => \App\Models\Setting::getValue('app.branding', []), []))
    @php($favicon = \App\Core\Support\AssetUrl::resolve($branding['favicon_url'] ?? null))
    @php($appIcon = \App\Core\Support\AssetUrl::resolve($branding['app_icon_url'] ?? null))
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="pharmanp-base-path" content="{{ request()->getBaseUrl() }}">
    <title>{{ $branding['app_name'] ?? config('app.name', 'PharmaNP') }}</title>
    @if ($favicon)
        <link rel="icon" href="{{ $favicon }}">
        <link rel="shortcut icon" href="{{ $favicon }}">
    @endif
    @if ($appIcon)
        <link rel="apple-touch-icon" href="{{ $appIcon }}">
    @endif
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body>
    @yield('body')
</body>
</html>
