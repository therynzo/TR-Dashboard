<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
    <link rel="stylesheet" href="{{ asset('tr-dashboard.css') }}?v=auth-intro-20261024-2">
    <style>:root{--accent:{{ $siteSettings['theme_color'] ?? '#7c3aed' }};}</style>
<style>
    html,body.auth-body{min-height:100%;width:100%;margin:0}
    #trAuthIntro.tr-auth-intro{position:fixed!important;inset:0!important;left:0!important;top:0!important;width:100vw!important;height:100vh!important;height:100dvh!important;max-width:none!important;max-height:none!important;overflow:hidden!important;display:block!important;z-index:2147483647!important;background:#03192e!important;isolation:isolate}
    #trAuthIntro.tr-auth-intro[hidden]{display:none!important}
    #trAuthIntro #trAuthIntroVideo.tr-auth-intro-video{position:absolute!important;inset:0!important;width:100%!important;height:100%!important;min-width:100%!important;min-height:100%!important;max-width:none!important;max-height:none!important;display:block!important;object-fit:cover!important;object-position:center center!important;border:0!important;margin:0!important;padding:0!important;background:#03192e!important;z-index:0!important}
    #trAuthIntro .tr-auth-intro-shade{position:absolute!important;inset:0!important;z-index:1!important;pointer-events:none}
    #trAuthIntro .tr-auth-intro-brand,#trAuthIntro .tr-auth-intro-actions,#trAuthIntro .tr-auth-intro-caption{z-index:2!important}
    .auth-card{position:relative;z-index:1}
    @media(max-width:640px){#trAuthIntro .tr-auth-intro-brand{left:18px;top:18px}#trAuthIntro .tr-auth-intro-actions{right:18px;top:18px}#trAuthIntro .tr-auth-intro-caption{left:18px;bottom:22px}}
  </style>
</head>
<body class="auth-body">
<div class="tr-auth-intro" id="trAuthIntro" aria-label="TR Dashboard intro" aria-hidden="false">
    <video id="trAuthIntroVideo" class="tr-auth-intro-video" autoplay playsinline preload="auto"  poster="{{ asset('tr-auth-wallpaper.svg') }}">
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

    // The clip is stored in small base64 chunks in the public folder to make
    // binary asset publication through GitHub Contents tooling reliable.
    const videoParts = [
        @json(asset('tr-login-intro-part-01.txt')),
        @json(asset('tr-login-intro-part-02.txt')),
        @json(asset('tr-login-intro-part-03.txt')),
        @json(asset('tr-login-intro-part-04.txt')),
        @json(asset('tr-login-intro-part-05.txt')),
        @json(asset('tr-login-intro-part-06.txt'))
    ];
    const startVideo = async () => {
        try {
            const contents = await Promise.all(videoParts.map(async (url) => {
                const response = await fetch(url, { cache: 'force-cache', credentials: 'same-origin' });
                if (!response.ok) throw new Error('Intro video part could not be loaded.');
                return (await response.text()).trim();
            }));
            const encoded = contents.join('').replace(/\\s+/g, '');
            const binary = atob(encoded);
            const bytes = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
            const objectUrl = URL.createObjectURL(new Blob([bytes], { type: 'video/mp4' }));
            video.src = objectUrl;
            video.load();
            video.volume = 0.85;
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
        } catch (error) {
            console.error('TR Dashboard intro playback failed:', error);
            finish();
        }
    };
    startVideo();
})();
</script>
</body>
</html>
