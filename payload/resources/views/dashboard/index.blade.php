@extends('layouts.app')
@section('title', 'Overview')
@section('crumb', 'Overview')
@section('content')
<div class="page-heading">
    <div><span class="eyebrow">YOUR CONTROL CENTER</span><h1>Dashboard overview</h1><p>Welcome back, {{ auth()->user()->username }}. Here's your hosting at a glance.</p></div>
    <div class="heading-actions"><a class="btn btn-outline" href="{{ route('credits') }}">◈ View credits</a><a class="btn btn-primary" href="{{ route('servers.create') }}">＋ Create server</a></div>
</div>
<div class="announce"><span class="announce-icon">i</span><span>{{ $siteSettings['announcement'] ?: 'Welcome to TR Dashboard.' }}</span><span style="margin-left:auto;opacity:.75">✦</span></div>
<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon icon-orange">▤</div><div class="stat-meta"><div class="stat-label">Your servers</div><div class="stat-value">{{ $activeCount }}<span style="font-size:13px;color:var(--muted);font-weight:550"> / {{ auth()->user()->server_slots }}</span></div><div class="stat-note">Active and suspended</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-green">◈</div><div class="stat-meta"><div class="stat-label">Credit balance</div><div class="stat-value">{{ number_format((float)auth()->user()->credits,2) }}</div><div class="stat-note">Available credits</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-blue">↻</div><div class="stat-meta"><div class="stat-label">Renewal cost</div><div class="stat-value">{{ number_format((float)\App\Support\Settings::get('server_renew_cost',0),2) }}</div><div class="stat-note">Credits per renewal</div></div></div>
    <div class="stat-card"><div class="stat-icon icon-purple">◷</div><div class="stat-meta"><div class="stat-label">Free server slots</div><div class="stat-value">{{ $remainingSlots }}</div><div class="stat-note">Available to deploy</div></div></div>
</div>
<div class="content-grid">
    <section class="panel">
        <div class="panel-head"><div><h2>Your servers</h2><p>Review status and renewal dates</p></div><a class="btn btn-secondary btn-small" href="{{ route('servers.index') }}">View all ↗</a></div>
        @if($servers->count())
            <div class="table-wrap"><table><thead><tr><th>Server</th><th>Status</th><th>Expires</th><th></th></tr></thead><tbody>
            @foreach($servers as $server)
                <tr><td><div class="table-main"><span class="mini-square">▤</span><div><a class="table-primary table-link" href="{{ route('servers.show',$server) }}">{{ $server->name }}</a><div class="table-secondary">ID #{{ $server->ptero_server_id ?: 'pending' }}</div></div></div></td><td><span class="badge {{ $server->status==='active'?'badge-green':($server->status==='suspended'?'badge-yellow':'badge-gray') }}">{{ ucfirst($server->status) }}</span></td><td>{{ $server->expires_at ? $server->expires_at->diffForHumans() : '—' }}</td><td><a class="table-link" href="{{ route('servers.show',$server) }}">Manage →</a></td></tr>
            @endforeach
            </tbody></table></div>
        @else
            <div class="empty-state"><div class="empty-graphic">▤</div><strong>Your first server starts here</strong><span>Deploy a server to see its status, usage, and renewal date.</span><div style="margin-top:15px"><a href="{{ route('servers.create') }}" class="btn btn-primary btn-small">Create free server</a></div></div>
        @endif
    </section>
    <section class="panel">
        <div class="panel-head"><div><h2>Quick actions</h2><p>Common tasks, one click away</p></div><span class="badge badge-blue">TR PANEL</span></div>
        <div class="panel-body">
            <div class="quick-actions">
                <a class="quick-action" href="{{ route('servers.create') }}"><span class="quick-icon">＋</span><span><strong>Create a server</strong><small>Deploy to Pterodactyl</small></span></a>
                <a class="quick-action" href="{{ route('credits') }}"><span class="quick-icon">◈</span><span><strong>Credit history</strong><small>View account transactions</small></span></a>
                <a class="quick-action" href="#redeem"><span class="quick-icon">◇</span><span><strong>Redeem a code</strong><small>Claim a giveaway reward</small></span></a>
                <a class="quick-action" href="{{ route('servers.index') }}"><span class="quick-icon">↻</span><span><strong>Renew a server</strong><small>Keep your server online</small></span></a>
                @if(\App\Support\Settings::enabled('linkvertise_enabled'))
                <a class="quick-action" href="{{ route('linkvertise.start') }}"><span class="quick-icon">↗</span><span><strong>Earn free credits</strong><small>Complete a verified sponsor task</small></span></a>
                @endif
            </div>
            <div id="redeem" style="margin-top:21px;padding-top:18px;border-top:1px solid var(--line)">
                <div class="section-heading-row"><h2 class="section-heading">Redeem a reward</h2><span class="muted text-small">Giveaway code</span></div>
                <form class="inline-form" method="POST" action="{{ route('redeem') }}">@csrf<input class="input" name="code" placeholder="XXXX-XXXX-XXXX" maxlength="40" required><button class="btn btn-primary" type="submit">Redeem</button></form>
            </div>
        </div>
    </section>
</div>
<div class="content-grid equal">
    <section class="panel"><div class="panel-head"><div><h2>Account details</h2><p>Your dashboard identity</p></div><span class="badge badge-green">{{ auth()->user()->ptero_user_id ? 'Pterodactyl linked' : 'Not linked' }}</span></div><div class="panel-body"><div class="data-list"><div class="data-row"><span>Username</span><strong>{{ auth()->user()->username }}</strong></div><div class="data-row"><span>Email</span><strong>{{ auth()->user()->email }}</strong></div><div class="data-row"><span>Member since</span><strong>{{ auth()->user()->created_at->format('M d, Y') }}</strong></div><div class="data-row"><span>Server lifetime</span><strong>{{ (int)\App\Support\Settings::get('server_lifetime_hours',48) }} hours</strong></div></div></div></section>
    <section class="panel"><div class="panel-head"><div><h2>Recent activity</h2><p>Updates for your account</p></div><span class="muted text-small">Latest 6</span></div>
        @if($logs->count())<div class="table-wrap"><table><thead><tr><th>Activity</th><th>Time</th></tr></thead><tbody>@foreach($logs as $log)<tr><td><div class="table-primary">{{ ucfirst(str_replace('_',' ',$log->action)) }}</div><div class="table-secondary">{{ $log->description }}</div></td><td>{{ $log->created_at->diffForHumans() }}</td></tr>@endforeach</tbody></table></div>@else<div class="empty-state"><strong>No recent activity</strong>Account events will appear here.</div>@endif
    </section>
</div>
@endsection
