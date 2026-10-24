@extends('layouts.app')
@section('title', 'Credits')
@section('crumb', 'Credits')
@section('content')
<div class="page-heading"><div><span class="eyebrow">ACCOUNT WALLET</span><h1>Credits</h1><p>Review your balance, credit transactions, and redeem rewards.</p></div><div class="heading-actions"><span class="badge badge-green">Wallet active</span></div></div>
<div class="stat-grid"><div class="stat-card"><div class="stat-icon icon-green">◈</div><div class="stat-meta"><div class="stat-label">Credit balance</div><div class="stat-value">{{ number_format((float)auth()->user()->credits,2) }}</div><div class="stat-note">Current available balance</div></div></div><div class="stat-card"><div class="stat-icon icon-purple">◇</div><div class="stat-meta"><div class="stat-label">Server renewal</div><div class="stat-value">{{ number_format((float)\App\Support\Settings::get('server_renew_cost',0),2) }}</div><div class="stat-note">Credits per renewal</div></div></div><div class="stat-card"><div class="stat-icon icon-blue">▤</div><div class="stat-meta"><div class="stat-label">Server creation</div><div class="stat-value">{{ number_format((float)\App\Support\Settings::get('server_create_cost',0),2) }}</div><div class="stat-note">Credits per new server</div></div></div><div class="stat-card"><div class="stat-icon icon-orange">⌂</div><div class="stat-meta"><div class="stat-label">Server slots</div><div class="stat-value">{{ auth()->user()->server_slots }}</div><div class="stat-note">Assigned to your account</div></div></div></div>
@php
    $linkvertiseDailyLimit = max(1, (int) \App\Support\Settings::get('linkvertise_daily_limit', 1));
    $linkvertiseClaimedToday = \App\Models\CreditTransaction::query()
        ->where('user_id', auth()->id())
        ->where('type', 'linkvertise_reward')
        ->where('created_at', '>=', now()->startOfDay())
        ->count();
    $linkvertiseRemaining = max(0, $linkvertiseDailyLimit - $linkvertiseClaimedToday);
    $linkvertiseReady = \App\Support\Settings::enabled('linkvertise_enabled')
        && trim((string) \App\Support\Settings::get('linkvertise_publisher_id', '')) !== ''
        && trim((string) \App\Support\Settings::get('linkvertise_url', '')) !== ''
        && trim((string) \App\Support\Settings::get('linkvertise_anti_bypass_token', '')) !== '';
@endphp
@if($linkvertiseReady)
<section class="panel" id="linkvertiseRewardPanel" style="margin:18px 0">
    <div class="panel-head">
        <div><h2>Earn free credits</h2><p>Complete a Linkvertise sponsor task to earn credits in your wallet.</p></div>
        <span class="badge badge-green" id="linkvertiseRewardBadge">+{{ number_format((float) \App\Support\Settings::get('linkvertise_reward_credits', 10), 2) }} credits</span>
    </div>
    <div class="panel-body">
        <div class="data-list">
            <div class="data-row"><span>Reward per verified task</span><strong>{{ number_format((float) \App\Support\Settings::get('linkvertise_reward_credits', 10), 2) }} credits</strong></div>
            <div class="data-row"><span>Daily reward limit</span><strong><span id="linkvertiseClaimedToday">{{ $linkvertiseClaimedToday }}</span> / <span id="linkvertiseDailyLimit">{{ $linkvertiseDailyLimit }}</span> claimed today</strong></div>
            <div class="data-row"><span>Rewards remaining today</span><strong id="linkvertiseRemaining">{{ $linkvertiseRemaining }}</strong></div>
        </div>
        <div style="height:8px;margin-top:14px;border-radius:999px;background:var(--line);overflow:hidden" role="progressbar" id="linkvertiseDailyProgress" aria-label="Daily Linkvertise reward usage" aria-valuemin="0" aria-valuemax="{{ $linkvertiseDailyLimit }}" aria-valuenow="{{ $linkvertiseClaimedToday }}">
            <div id="linkvertiseDailyProgressFill" style="height:100%;width:{{ min(100, ($linkvertiseClaimedToday / $linkvertiseDailyLimit) * 100) }}%;border-radius:999px;background:linear-gradient(90deg,#7c3aed,#35b7ff);transition:width .3s ease"></div>
        </div>
        <p id="linkvertiseDailyStatus" class="section-note" style="margin-top:14px" aria-live="polite">
            @if($linkvertiseRemaining > 0)
                You can claim {{ $linkvertiseRemaining }} more reward{{ $linkvertiseRemaining === 1 ? '' : 's' }} today. This counter refreshes automatically.
            @else
                You've reached today's Linkvertise reward limit. It resets at midnight server time.
            @endif
        </p>
        <a class="btn btn-primary" id="linkvertiseStartButton" href="{{ route('linkvertise.start') }}" @if($linkvertiseRemaining < 1) hidden aria-disabled="true" @endif>Complete task &amp; earn credits ↗</a>
    </div>
</section>
<script>
(() => {
    const endpoint = @json(route('linkvertise.status'));
    const claimed = document.getElementById('linkvertiseClaimedToday');
    const limit = document.getElementById('linkvertiseDailyLimit');
    const remaining = document.getElementById('linkvertiseRemaining');
    const progress = document.getElementById('linkvertiseDailyProgress');
    const fill = document.getElementById('linkvertiseDailyProgressFill');
    const status = document.getElementById('linkvertiseDailyStatus');
    const startButton = document.getElementById('linkvertiseStartButton');
    let busy = false;

    async function refreshLinkvertiseStatus() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(endpoint, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) return;
            const data = await response.json();
            if (!data.enabled) return;

            const claimedCount = Math.max(0, Number(data.claimed_today) || 0);
            const dailyLimit = Math.max(1, Number(data.daily_limit) || 1);
            const rewardsLeft = Math.max(0, Number(data.remaining) || 0);
            const percent = Math.min(100, (claimedCount / dailyLimit) * 100);

            if (claimed) claimed.textContent = String(claimedCount);
            if (limit) limit.textContent = String(dailyLimit);
            if (remaining) remaining.textContent = String(rewardsLeft);
            if (progress) {
                progress.setAttribute('aria-valuemax', String(dailyLimit));
                progress.setAttribute('aria-valuenow', String(claimedCount));
            }
            if (fill) fill.style.width = percent + '%';
            if (status) {
                status.textContent = rewardsLeft > 0
                    ? 'You can claim ' + rewardsLeft + ' more reward' + (rewardsLeft === 1 ? '' : 's') + ' today. This counter refreshes automatically.'
                    : "You've reached today's Linkvertise reward limit. It resets at midnight server time.";
            }
            if (startButton) {
                startButton.hidden = rewardsLeft < 1;
                startButton.setAttribute('aria-disabled', rewardsLeft < 1 ? 'true' : 'false');
            }
        } catch (_) {
            // Keep the last successful status visible if a temporary network error occurs.
        } finally {
            busy = false;
        }
    }

    refreshLinkvertiseStatus();
    window.setInterval(refreshLinkvertiseStatus, 15000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshLinkvertiseStatus();
    });
})();
</script>
@elseif(auth()->user()->isAdmin())
<section class="panel" style="margin:18px 0">
    <div class="panel-head"><div><h2>Linkvertise rewards are hidden</h2><p>The reward card appears for customers only after the integration is fully configured and enabled.</p></div></div>
    <div class="panel-body">
        <p class="section-note">Check that Publisher ID, the published Linkvertise URL, and the 64-character anti-bypass token are saved, then enable the integration.</p>
        <a class="btn btn-secondary" href="{{ route('admin.settings.linkvertise') }}">Configure Linkvertise rewards →</a>
    </div>
</section>
@endif
<div class="content-grid equal"><section class="panel"><div class="panel-head"><div><h2>Redeem a code</h2><p>Enter a giveaway or reward code</p></div></div><div class="panel-body"><form method="POST" action="{{ route('redeem') }}">@csrf<div class="field"><label for="code">Reward code</label><input class="input mono" id="code" name="code" placeholder="XXXX-XXXX-XXXX" maxlength="40" required></div><button class="btn btn-primary" type="submit">Claim reward →</button></form><div class="alert alert-success" style="margin:18px 0 0"><span>i</span><div>Codes may award credits, extra server slots, or both. Each account can redeem each code once.</div></div></div></section>
<section class="panel"><div class="panel-head"><div><h2>Credit settings</h2><p>How your balance is used</p></div></div><div class="panel-body"><div class="data-list"><div class="data-row"><span>New account reward</span><strong>{{ number_format((float)\App\Support\Settings::get('default_credits',100),2) }} credits</strong></div><div class="data-row"><span>Default server slots</span><strong>{{ (int)\App\Support\Settings::get('default_server_slots',1) }}</strong></div><div class="data-row"><span>Renewal lifetime</span><strong>{{ (int)\App\Support\Settings::get('server_lifetime_hours',48) }} hours</strong></div><div class="data-row"><span>Suspended grace period</span><strong>{{ (int)\App\Support\Settings::get('server_grace_days',7) }} days</strong></div></div></div></section></div>
<section class="panel"><div class="panel-head"><div><h2>Credit transactions</h2><p>Account balance history and adjustments</p></div><span class="muted text-small">{{ $transactions->total() }} records</span></div>
@if($transactions->count())<div class="table-wrap"><table><thead><tr><th>Transaction</th><th>Type</th><th>Amount</th><th>Balance after</th><th>Date</th></tr></thead><tbody>@foreach($transactions as $transaction)<tr><td><div class="table-primary">{{ $transaction->description }}</div><div class="table-secondary">Transaction #{{ $transaction->id }}</div></td><td><span class="badge badge-gray">{{ ucfirst(str_replace('_',' ',$transaction->type)) }}</span></td><td style="color:{{ (float)$transaction->amount<0?'#ff8b96':'#55df9b' }};font-weight:700">{{ (float)$transaction->amount>0?'+':'' }}{{ number_format((float)$transaction->amount,2) }}</td><td>{{ number_format((float)$transaction->balance_after,2) }}</td><td>{{ $transaction->created_at->format('M d, Y H:i') }}</td></tr>@endforeach</tbody></table></div><div class="pagination"><span>Showing {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} of {{ $transactions->total() }}</span>{{ $transactions->links() }}</div>@else<div class="empty-state"><strong>No transactions yet</strong>Your credit activity will be recorded here.</div>@endif
</section>
@endsection
