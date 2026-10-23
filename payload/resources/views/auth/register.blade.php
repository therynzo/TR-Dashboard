@extends('layouts.auth')
@section('title', 'Create account')
@section('content')
    <h1>Create account</h1>
    <p class="auth-subtitle">Set up your account and get started with your free server slot.</p>
    <form method="POST" action="{{ route('register.submit') }}">
        @csrf
        <div class="field"><label for="username">Username</label><input class="input" id="username" name="username" value="{{ old('username') }}" placeholder="Your username" autocomplete="username" minlength="3" maxlength="32" required></div>
        <div class="field"><label for="email">Email address</label><input class="input" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" required></div>
        <div class="field-row">
            <div class="field"><label for="password">Password</label><input class="input" id="password" name="password" type="password" placeholder="At least 8 characters" autocomplete="new-password" minlength="8" required></div>
            <div class="field"><label for="password_confirmation">Confirm password</label><input class="input" id="password_confirmation" name="password_confirmation" type="password" placeholder="Repeat password" autocomplete="new-password" minlength="8" required></div>
        </div>
        <label class="checkbox-row"><input type="checkbox" name="terms" value="1" required><span>I agree to the <a href="#terms">Terms of Service</a> and responsible-use policy.</span></label>
        <button class="btn btn-primary btn-full" type="submit">Create account <span>→</span></button>
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
    <div class="auth-meta">Already registered? <a href="{{ route('login') }}">Sign in</a></div>
@endsection
