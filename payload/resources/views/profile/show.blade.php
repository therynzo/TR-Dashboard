@extends('layouts.app')
@section('title','My Profile')
@section('crumb','My Profile')
@section('content')
<div class="page-heading"><div><div class="eyebrow">ACCOUNT</div><h1>My profile</h1><p>View your signed-in email, update account details, and change your password.</p></div></div>
<section class="settings-card"><h3>Account details</h3><div class="data-row"><span>Your account email</span><strong>{{ $account->email }}</strong></div><div class="data-row"><span>Account type</span><strong>{{ $account->isAdmin() ? 'Administrator' : 'Client' }}</strong></div><div class="data-row"><span>External sign-in</span><strong>{{ $account->discord_oauth_id ? 'Discord' : '' }}{{ $account->discord_oauth_id && $account->google_oauth_id ? ' + ' : '' }}{{ $account->google_oauth_id ? 'Google' : '' }}{{ !$account->discord_oauth_id && !$account->google_oauth_id ? 'Email and password' : '' }}</strong></div><div class="data-row"><span>Joined</span><strong>{{ optional($account->created_at)->format('M j, Y') }}</strong></div>
<form method="POST" action="{{ route('profile.update') }}" style="margin-top:18px">@csrf @method('PUT')
<div class="field"><label for="username">Username</label><input class="input" id="username" name="username" value="{{ old('username',$account->username) }}" minlength="3" maxlength="32" required></div>
<div class="field"><label for="email">Email address</label><input class="input" id="email" type="email" name="email" value="{{ old('email',$account->email) }}" required></div>
<div class="form-actions"><button class="btn btn-primary" type="submit">Save profile <span>✓</span></button></div>
</form></section>
<section class="settings-card" style="margin-top:16px"><h3>Change password</h3><p class="settings-desc">Use a strong unique password with at least eight characters.</p>
<form method="POST" action="{{ route('profile.password') }}">@csrf @method('PUT')
@if($account->local_password_set)
<div class="field"><label for="current_password">Current password</label><input class="input" id="current_password" type="password" name="current_password" autocomplete="current-password" required></div>
@else
<p class="section-note">No local password is set yet. You can create one now; future password changes will require your current password.</p>
@endif
<div class="field-row"><div class="field"><label for="password">New password</label><input class="input" id="password" type="password" name="password" autocomplete="new-password" minlength="8" required></div><div class="field"><label for="password_confirmation">Confirm new password</label><input class="input" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></div></div>
<div class="form-actions"><button class="btn btn-primary" type="submit">Update password <span>✓</span></button></div>
</form></section>
@endsection
