<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php($branding = rescue(fn () => \App\Models\Setting::getValue('app.branding', []), []))
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - {{ $branding['app_name'] ?? config('app.name', 'PharmaNP') }}</title>
    @if (! empty($branding['favicon_url']))
        <link rel="icon" href="{{ $branding['favicon_url'] }}">
    @endif
    @vite(['resources/css/app.css'])
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="auth-brand">
            @if (! empty($branding['app_icon_url']))
                <img src="{{ $branding['app_icon_url'] }}" alt="{{ $branding['app_name'] ?? 'PharmaNP' }}">
            @endif
            <span>{{ $branding['app_name'] ?? 'PharmaNP' }}</span>
            <strong>ERP Access</strong>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="auth-form">
            @csrf
            <label>
                Email
                <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required>
            </label>

            <label>
                Password
                <input name="password" type="password" autocomplete="current-password" required>
            </label>

            <label class="auth-check">
                <input name="remember" type="checkbox" value="1">
                <span>Keep me signed in</span>
            </label>

            @if ($errors->any())
                <div class="auth-error">{{ $errors->first() }}</div>
            @endif

            <button type="submit">Sign in</button>
        </form>
    </main>
</body>
</html>
