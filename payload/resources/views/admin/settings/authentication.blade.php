@extends('layouts.app')
@section('title','Authentication')
@section('crumb','Admin / Authentication')
@section('content')
<div class="page-heading"><div><div class="eyebrow">OPTIONAL SIGN-IN</div><h1>Authentication providers</h1><p>Discord and Google login/register are off by default. Add credentials and callbacks, save, then enable each provider.</p></div></div>
<form method="POST" action="{{ route('admin.settings.authentication.save') }}">@csrf
<section class="settings-card"><h3>Discord OAuth</h3><p class="settings-desc">Create an application in the Discord Developer Portal and add the exact callback below under OAuth2 Redirects. Scopes required: identify and email.</p>
  <div class="field"><label class="checkbox-row" style="margin:0"><input type="checkbox" name="discord_oauth_enabled" value="1" @checked((bool)$settings['discord_oauth_enabled'])><span>Enable Discord sign-in and registration</span></label></div>
  <div class="field"><label>Callback URL (paste into Discord Developer Portal)</label><div class="copy-field"><input class="input mono" readonly value="{{ $settings['discord_callback_url'] }}"><button class="btn btn-secondary" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">Copy</button></div></div>
  <div class="field"><label for="discord_oauth_client_id">Client ID</label><input class="input mono" id="discord_oauth_client_id" name="discord_oauth_client_id" value="{{ old('discord_oauth_client_id',$settings['discord_oauth_client_id']) }}"></div>
  <div class="field"><label for="discord_oauth_secret">Client Secret</label><input class="input mono" type="password" autocomplete="new-password" id="discord_oauth_secret" name="discord_oauth_secret" value="" placeholder="{{ $settings['discord_oauth_secret_configured'] ? 'Secret saved — leave blank to keep it' : 'Paste Discord client secret' }}"><div class="field-help">@if($settings['discord_oauth_secret_configured'])Secret stored encrypted. @else No secret saved yet.@endif</div></div>
</section>
<section class="settings-card" style="margin-top:16px"><h3>Google OAuth</h3><p class="settings-desc">Create a Google OAuth web client. Add the callback URL below as an Authorized redirect URI.</p>
  <div class="field"><label class="checkbox-row" style="margin:0"><input type="checkbox" name="google_oauth_enabled" value="1" @checked((bool)$settings['google_oauth_enabled'])><span>Enable Google sign-in and registration</span></label></div>
  <div class="field"><label>Callback URL (paste into Google Cloud Console)</label><div class="copy-field"><input class="input mono" readonly value="{{ $settings['google_callback_url'] }}"><button class="btn btn-secondary" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">Copy</button></div></div>
  <div class="field"><label for="google_oauth_client_id">Client ID</label><input class="input mono" id="google_oauth_client_id" name="google_oauth_client_id" value="{{ old('google_oauth_client_id',$settings['google_oauth_client_id']) }}"></div>
  <div class="field"><label for="google_oauth_secret">Client Secret</label><input class="input mono" type="password" autocomplete="new-password" id="google_oauth_secret" name="google_oauth_secret" value="" placeholder="{{ $settings['google_oauth_secret_configured'] ? 'Secret saved — leave blank to keep it' : 'Paste Google client secret' }}"><div class="field-help">@if($settings['google_oauth_secret_configured'])Secret stored encrypted. @else No secret saved yet.@endif</div></div>
</section>
<div class="form-actions"><button class="btn btn-primary" type="submit">Save authentication settings <span>✓</span></button></div>
</form>
@endsection
