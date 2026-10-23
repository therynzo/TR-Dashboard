@extends('layouts.auth')
@section('title', 'Sign in')
@section('content')
    <h1>Welcome back</h1>
    <p class="auth-subtitle">Sign in to manage your servers and credits.</p>
    <form method="POST" action="{{ route('login.submit') }}">
        @csrf
        <div class="field"><label for="identity">Email or username</label><input class="input" id="identity" name="identity" value="{{ old('identity') }}" placeholder="you@example.com" autocomplete="username" required autofocus></div>
        <div class="field"><label for="password">Password</label><input class="input" id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required></div>
        <label class="checkbox-row"><input type="checkbox" name="remember" value="1"><span>Keep me signed in on this device</span></label>
        <button class="btn btn-primary btn-full" type="submit">Sign in <span>→</span></button>
    </form>
    @if(($oauthProviders['discord'] ?? false) || ($oauthProviders['google'] ?? false))
        <div class="oauth-buttons" style="display:grid;gap:9px;margin-top:16px">
            @if($oauthProviders['discord'] ?? false)
                <a class="btn btn-outline btn-full" href="{{ route('oauth.redirect', ['provider' => 'discord']) }}">Continue with Discord <span>↗</span></a>
            @endif
            @if($oauthProviders['google'] ?? false)
                <a class="btn btn-outline btn-full" href="{{ route('oauth.redirect', ['provider' => 'google']) }}">Continue with Google <span>↗</span></a>
            @endif
        </div>
    @endif
    <div class="auth-divider"></div>
    <div class="auth-meta">New to TR Dashboard? <a href="{{ route('register') }}">Create an account</a></div>
@endsection
