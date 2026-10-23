<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ $siteSettings['brand_name'] ?? 'TR Dashboard' }}</title>
    <link rel="icon" type="image/png" href="{{ !empty($siteSettings['logo_url']) ? $siteSettings['logo_url'] : asset('tr-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ !empty($siteSettings['logo_url']) ? $siteSettings['logo_url'] : asset('tr-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('tr-dashboard.css') }}">
    <style>:root{--accent:{{ $siteSettings['theme_color'] ?? '#7c3aed' }};}</style>
</head>
<body class="dashboard-body">
@php($currentUser = auth()->user())
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a href="{{ route('dashboard') }}" class="brand">
            <img class="brand-logo" src="{{ !empty($siteSettings['logo_url']) ? $siteSettings['logo_url'] : asset('tr-logo.png') }}" alt="TR Dashboard logo">
            <span class="brand-text">{{ $siteSettings['brand_name'] ?? 'TR Dashboard' }}</span>
        </a>
        <div class="side-caption">WORKSPACE</div>
        <nav class="side-nav">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span class="nav-ico">⌂</span><span>Overview</span></a>
            <a class="nav-link {{ request()->routeIs('servers.*') && !request()->routeIs('admin.servers') ? 'active' : '' }}" href="{{ route('servers.index') }}"><span class="nav-ico">▤</span><span>Servers</span><span class="nav-count">{{ $currentUser->servers()->whereIn('status', ['active','suspended'])->count() }}/{{ $currentUser->server_slots }}</span></a>
            <a class="nav-link {{ request()->routeIs('credits') ? 'active' : '' }}" href="{{ route('credits') }}"><span class="nav-ico">◈</span><span>Credits</span></a>
            <a class="nav-link {{ request()->routeIs('tickets.*') ? 'active' : '' }}" href="{{ route('tickets.index') }}"><span class="nav-ico">▧</span><span>Support Tickets</span></a>
            <a class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.show') }}"><span class="nav-ico">♙</span><span>My Profile</span></a>
            <a class="nav-link" href="{{ !empty($siteSettings['discord_url']) ? $siteSettings['discord_url'] : '#' }}" @if(!empty($siteSettings['discord_url'])) target="_blank" rel="noopener" @endif><span class="nav-ico">◌</span><span>Discord</span></a>
        </nav>
        @if($currentUser->isAdmin())
            <div class="side-caption side-caption-gap">ADMINISTRATION</div>
            <nav class="side-nav">
                <a class="nav-link {{ request()->routeIs('admin.overview') ? 'active' : '' }}" href="{{ route('admin.overview') }}"><span class="nav-ico">◷</span><span>Admin Overview</span></a>
                <a class="nav-link {{ request()->routeIs('admin.users') || request()->routeIs('admin.users.update') ? 'active' : '' }}" href="{{ route('admin.users') }}"><span class="nav-ico">♙</span><span>Users</span></a>
                <a class="nav-link {{ request()->routeIs('admin.servers') ? 'active' : '' }}" href="{{ route('admin.servers') }}"><span class="nav-ico">▦</span><span>All Servers</span></a>
                <a class="nav-link {{ request()->routeIs('admin.tickets') ? 'active' : '' }}" href="{{ route('admin.tickets') }}"><span class="nav-ico">▧</span><span>Ticket List</span></a>
                <a class="nav-link {{ request()->routeIs('admin.redeem-codes') ? 'active' : '' }}" href="{{ route('admin.redeem-codes') }}"><span class="nav-ico">◇</span><span>Redeem Codes</span></a>
                <a class="nav-link {{ request()->routeIs('admin.settings.branding*') ? 'active' : '' }}" href="{{ route('admin.settings.branding') }}"><span class="nav-ico">✦</span><span>Branding</span></a>
                <a class="nav-link {{ request()->routeIs('admin.settings.pterodactyl*') ? 'active' : '' }}" href="{{ route('admin.settings.pterodactyl') }}"><span class="nav-ico">⛭</span><span>Pterodactyl API</span></a>
                <a class="nav-link {{ request()->routeIs('admin.settings.defaults*') ? 'active' : '' }}" href="{{ route('admin.settings.defaults') }}"><span class="nav-ico">◈</span><span>Credits & Defaults</span></a>
                <a class="nav-link {{ request()->routeIs('admin.settings.authentication*') ? 'active' : '' }}" href="{{ route('admin.settings.authentication') }}"><span class="nav-ico">♙</span><span>Authentication</span></a>
                <a class="nav-link {{ request()->routeIs('admin.settings.linkvertise*') ? 'active' : '' }}" href="{{ route('admin.settings.linkvertise') }}"><span class="nav-ico">↗</span><span>Linkvertise Rewards</span></a>
                <a class="nav-link {{ request()->routeIs('admin.logs') ? 'active' : '' }}" href="{{ route('admin.logs') }}"><span class="nav-ico">≡</span><span>Activity Logs</span></a>
            </nav>
        @endif
        <div class="sidebar-bottom">
            <div class="sidebar-status"><span class="status-dot"></span><span>Dashboard online</span></div>
            <div class="side-user">
                <span class="avatar">{{ strtoupper(substr($currentUser->username,0,1)) }}</span>
                <span class="side-user-details"><strong>{{ $currentUser->username }}</strong><small>{{ $currentUser->isAdmin() ? 'Administrator' : 'Client account' }}</small></span>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-button logout-button" aria-label="Sign out" title="Sign out">⇥</button></form>
            </div>
        </div>
    </aside>

    <div class="main-shell">
        <header class="topbar">
            <button class="mobile-menu icon-button" type="button" onclick="document.getElementById('sidebar').classList.toggle('sidebar-open')" aria-label="Toggle menu">☰</button>
            <div class="topbar-left"><img class="topbar-logo" src="{{ !empty($siteSettings['logo_url']) ? $siteSettings['logo_url'] : asset('tr-logo.png') }}" alt="TR Dashboard logo"><span class="breadcrumb-divider">/</span><span>@yield('crumb', 'Overview')</span></div>
            <div class="topbar-right">
                <a class="topbar-link" href="{{ $siteSettings['ptero_url'] ?: '#' }}" @if(!empty($siteSettings['ptero_url'])) target="_blank" rel="noopener" @endif><span>↗</span> Pterodactyl</a>
                <span class="topbar-divider"></span>
                <span class="topbar-credit"><span class="coin">◈</span>{{ number_format((float)$currentUser->credits, 2) }} credits</span>
                <span class="avatar avatar-small">{{ strtoupper(substr($currentUser->username,0,1)) }}</span>
            </div>
        </header>
        <main class="page-content">
            @if(session('success')) <div class="alert alert-success"><span>✓</span><div>{{ session('success') }}</div></div> @endif
            @if(session('error')) <div class="alert alert-error"><span>!</span><div>{{ session('error') }}</div></div> @endif
            @if($errors->any()) <div class="alert alert-error"><span>!</span><div><strong>Please review the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div> @endif
            @yield('content')
        </main>
        <footer class="app-footer"><span class="footer-brand"><img class="footer-logo" src="{{ !empty($siteSettings['logo_url']) ? $siteSettings['logo_url'] : asset('tr-logo.png') }}" alt=""><span>© {{ date('Y') }} {{ $siteSettings['brand_name'] ?? 'TR Dashboard' }}. All rights reserved.</span></span><span>Officially made by <strong>TheRynzo</strong> · <a href="{{ $siteSettings['ptero_url'] ?: '#' }}" @if(!empty($siteSettings['ptero_url'])) target="_blank" rel="noopener" @endif>Pterodactyl API</a></span></footer>
    </div>
</div>
</body>
</html>
