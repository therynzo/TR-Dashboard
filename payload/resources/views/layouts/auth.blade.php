<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Account') · {{ $siteSettings['brand_name'] ?? 'TR Dashboard' }}</title>
    <link rel="icon" type="image/png" href="{{ !empty($siteSettings['logo_url']) ? $siteSettings['logo_url'] : asset('tr-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ !empty($siteSettings['logo_url']) ? $siteSettings['logo_url'] : asset('tr-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('tr-dashboard.css') }}">
    <style>:root{--accent:{{ $siteSettings['theme_color'] ?? '#7c3aed' }};}</style>
</head>
<body class="auth-body">
<div class="auth-card">
    <section class="auth-art">
        <a href="{{ route('login') }}" class="brand auth-brand">
            <img class="brand-logo" src="{{ !empty($siteSettings['logo_url']) ? $siteSettings['logo_url'] : asset('tr-logo.png') }}" alt="TR Dashboard logo">
            <span class="brand-text">{{ $siteSettings['brand_name'] ?? 'TR Dashboard' }}</span>
        </a>
        <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
        <div class="art-cubes"><span class="cube cube-a"></span><span class="cube cube-b"></span><span class="cube cube-c"></span><span class="cube cube-d"></span><span class="cube cube-e"></span><span class="cube cube-f"></span></div>
        <div class="art-copy"><span class="eyebrow">YOUR HOSTING. YOUR CONTROL.</span><h2>Build something<br><span>extraordinary.</span></h2><p>One clean dashboard for your game servers, credits, and account.</p></div>
        <div class="art-footer"><span class="status-dot"></span> Secure server management</div>
    </section>
    <section class="auth-form-wrap">
        <div class="auth-form-inner">
            <div class="auth-mobile-brand"><img class="brand-logo" src="{{ !empty($siteSettings['logo_url']) ? $siteSettings['logo_url'] : asset('tr-logo.png') }}" alt="TR Dashboard logo"> {{ $siteSettings['brand_name'] ?? 'TR Dashboard' }}</div>
            @if(session('success')) <div class="alert alert-success"><span>✓</span>{{ session('success') }}</div> @endif
            @if(session('error')) <div class="alert alert-error"><span>!</span>{{ session('error') }}</div> @endif
            @if($errors->any()) <div class="alert alert-error"><span>!</span><div><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div> @endif
            @yield('content')
            <div class="auth-legal">Officially made by <strong>TheRynzo</strong> <span>·</span> TR Dashboard</div>
        </div>
    </section>
</div>
</body>
</html>
