@extends('layouts.app')
@section('title','Branding')
@section('crumb','Admin / Branding')
@section('content')
<div class="page-heading"><div><div class="eyebrow">ADMIN CONFIGURATION</div><h1>Branding</h1><p>Panel name, logo, accent color, announcement and community link.</p></div></div>
<form method="POST" action="{{ route('admin.settings.branding.save') }}" enctype="multipart/form-data">@csrf
  <section class="settings-card">
    <h3>Dashboard identity</h3><p class="settings-desc">These settings update the shared dashboard, authentication pages, and browser icon.</p>
    <div class="field"><label for="brand_name">Panel name</label><input class="input" id="brand_name" name="brand_name" value="{{ old('brand_name',$settings['brand_name']) }}" maxlength="60" required></div>
    <div class="field"><label for="theme_color">Accent color</label><div class="field-row"><input style="height:42px;padding:3px;max-width:70px" type="color" value="{{ old('theme_color',$settings['theme_color']) }}" oninput="document.getElementById('theme_color').value=this.value"><input class="input mono" id="theme_color" name="theme_color" value="{{ old('theme_color',$settings['theme_color']) }}" pattern="^#[0-9A-Fa-f]{6}$" required></div><div class="field-help">Default is purple (#7c3aed). Choose any six-digit hex color.</div></div>
    <div class="field"><label for="logo">Upload replacement logo (optional)</label><input class="input" id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"><div class="field-help">PNG, JPG or WebP; 2 MB maximum. The supplied white TheRynzo flower logo is used by default across the panel.</div><label class="checkbox-row" style="margin-top:10px"><input type="checkbox" name="reset_logo" value="1"><span>Reset to the bundled TheRynzo flower logo instead of keeping the current custom logo</span></label></div>
    <div class="field"><label>Current logo</label><div style="display:flex;align-items:center;gap:12px"><img src="{{ $settings['logo_url'] ?: asset('tr-logo.png') }}" alt="Current logo" style="width:56px;height:56px;object-fit:contain;background:#0b0c11;border:1px solid var(--line);border-radius:10px;padding:6px"><span class="muted text-small">{{ $settings['logo_url'] ?: '/tr-logo.png' }}</span></div></div>
    <div class="field"><label for="announcement">Dashboard announcement</label><textarea class="textarea" id="announcement" name="announcement" maxlength="500">{{ old('announcement',$settings['announcement']) }}</textarea></div>
    <div class="field"><label for="discord_url">Support Discord invite (optional)</label><input class="input" id="discord_url" name="discord_url" type="url" value="{{ old('discord_url',$settings['discord_url']) }}" placeholder="https://discord.gg/yourserver"></div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Save branding <span>✓</span></button></div>
  </section>
</form>
@endsection
