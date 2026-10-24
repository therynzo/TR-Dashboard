<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
    (() => {
        try {
            const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduced || sessionStorage.getItem('tr-auth-intro-v1') === 'done') {
                document.documentElement.classList.add('tr-auth-revealed');
            } else {
                document.documentElement.classList.add('tr-intro-pending');
            }
        } catch (_) {
            document.documentElement.classList.add('tr-auth-revealed');
        }
    })();
    </script>
    <title>@yield('title', 'Account') · {{ $siteSettings['brand_name'] ?? 'TR Dashboard' }}</title>
    <link rel="icon" type="image/png" href="{{ !empty($siteSettings['logo_url']) ? $siteSettings['logo_url'] : asset('tr-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ !empty($siteSettings['logo_url']) ? $siteSettings['logo_url'] : asset('tr-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('tr-dashboard.css') }}">
    <style>:root{--accent:{{ $siteSettings['theme_color'] ?? '#7c3aed' }};}</style>
</head>
<body class="auth-body">
<div class="tr-auth-intro" id="trAuthIntro" aria-label="TR Dashboard intro" aria-hidden="false">
    <video id="trAuthIntroVideo" class="tr-auth-intro-video" autoplay playsinline preload="auto" src="{{ asset('tr-login-intro.mp4') }}" poster="{{ asset('tr-auth-wallpaper.svg') }}">
    </video>
    <div class="tr-auth-intro-shade" aria-hidden="true"></div>
    <div class="tr-auth-intro-brand">
        <img src="{{ !empty($siteSettings['logo_url']) ? $siteSettings['logo_url'] : asset('tr-logo.png') }}" alt="" width="34" height="34">
        <span>{{ $siteSettings['brand_name'] ?? 'TR Dashboard' }}</span>
        <small>Officially made by TheRynzo</small>
    </div>
    <div class="tr-auth-intro-actions">
        <button type="button" id="trAuthSound" class="tr-auth-intro-button" hidden>Enable sound</button>
        <button type="button" id="trAuthSkip" class="tr-auth-intro-button tr-auth-intro-skip">Skip intro <span aria-hidden="true">→</span></button>
    </div>
    <div class="tr-auth-intro-caption"><span class="tr-auth-loading-dot"></span> Preparing your dashboard</div>
</div>
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
<script>
(() => {
    const root = document.documentElement;
    const overlay = document.getElementById('trAuthIntro');
    const video = document.getElementById('trAuthIntroVideo');
    const soundButton = document.getElementById('trAuthSound');
    const skipButton = document.getElementById('trAuthSkip');
    if (!overlay || !video) return;
    if (!root.classList.contains('tr-intro-pending')) {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        return;
    }

    const markSeen = () => { try { sessionStorage.setItem('tr-auth-intro-v1', 'done'); } catch (_) {} };
    let finished = false;
    let fallbackTimer = null;
    let objectUrl = null;
    const finish = () => {
        if (finished) return;
        finished = true;
        markSeen();
        if (fallbackTimer) window.clearTimeout(fallbackTimer);
        root.classList.remove('tr-intro-pending');
        root.classList.add('tr-auth-revealed');
        overlay.classList.add('tr-auth-intro-ending');
        overlay.setAttribute('aria-hidden', 'true');
        try { video.pause(); } catch (_) {}
        window.setTimeout(() => { overlay.hidden = true; }, 750);
    };

    skipButton?.addEventListener('click', finish);
    soundButton?.addEventListener('click', async () => {
        try {
            video.muted = false;
            video.volume = 0.85;
            await video.play();
            soundButton.hidden = true;
        } catch (_) {
            soundButton.textContent = 'Tap to retry sound';
        }
    });
    video.addEventListener('ended', finish, { once: true });
    video.addEventListener('error', finish, { once: true });

    // Try audible autoplay first; browsers may require a user gesture for sound.
    video.volume = 0.85;
    let playFallbackTimer = window.setTimeout(finish, 18000);
    const startVideo = async () => {
        try {
            video.load();
            try {
                await video.play();
            } catch (_) {
                video.muted = true;
                if (soundButton) {
                    soundButton.hidden = false;
                    soundButton.textContent = 'Enable sound';
                }
                try {
                    await video.play();
                } catch (_) {
                    if (soundButton) soundButton.textContent = 'Play intro with sound';
                    finish();
                }
            }
        } catch (_) {
            finish();
        }
    };
    startVideo();
})();
</script>
</body>
</html>
