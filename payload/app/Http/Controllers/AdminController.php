<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CreditTransaction;
use App\Models\RedeemCode;
use App\Models\ServerInstance;
use App\Models\SupportTicket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\PterodactylClient;
use App\Support\ActivityLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AdminController extends Controller
{
    public function overview(): View
    {
        return view('admin.overview', [
            'stats' => [
                'users' => User::count(),
                'servers' => ServerInstance::whereIn('status', ['active', 'suspended'])->count(),
                'active' => ServerInstance::where('status', 'active')->count(),
                'credits' => (float) User::sum('credits'),
            ],
            'latestUsers' => User::latest()->limit(6)->get(),
            'latestServers' => ServerInstance::with('user')->latest()->limit(6)->get(),
            'latestLogs' => ActivityLog::latest()->limit(8)->get(),
        ]);
    }

    public function users(Request $request): View
    {
        $query = User::query()->withCount(['servers as active_servers_count' => fn ($q) => $q->whereIn('status', ['active', 'suspended'])]);
        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(fn ($q) => $q->where('username', 'like', '%' . $search . '%')->orWhere('email', 'like', '%' . $search . '%'));
        }
        return view('admin.users', ['users' => $query->orderByDesc('created_at')->paginate(15)->withQueryString(), 'search' => $search ?? '']);
    }

    public function updateUser(Request $request, User $user, PterodactylClient $client): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9_]+$/', 'unique:users,username,' . $user->id],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email,' . $user->id],
            'credits' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'server_slots' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        $usernameChanged = $data['username'] !== $user->username;
        $emailChanged = $data['email'] !== $user->email;
        if ($user->ptero_user_id && ($usernameChanged || $emailChanged)) {
            try {
                $client->updateUser((int) $user->ptero_user_id, [
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'first_name' => $data['username'],
                    'last_name' => 'User',
                    'root_admin' => false,
                ]);
            } catch (Throwable $e) {
                report($e);
                return back()->with('error', 'Could not sync the profile change to Pterodactyl. The local account was not changed. Check API permissions and the remote username/email uniqueness.');
            }
        }

        DB::transaction(function () use ($user, $data) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $oldCredits = (float) $locked->credits;
            $newCredits = round((float) $data['credits'], 2);
            $delta = round($newCredits - $oldCredits, 2);
            $locked->name = $data['username'];
            $locked->username = $data['username'];
            $locked->email = $data['email'];
            $locked->credits = $newCredits;
            $locked->server_slots = (int) $data['server_slots'];
            $locked->save();
            if (abs($delta) > 0.001) {
                CreditTransaction::create([
                    'user_id' => $locked->id,
                    'amount' => $delta,
                    'balance_after' => $newCredits,
                    'type' => 'admin_adjustment',
                    'description' => 'Credit balance adjusted by administrator',
                ]);
            }
        });

        ActivityLogger::record($request->user()->id, $request->user()->username, 'user_updated', 'Updated profile, credits, or server slots for user #' . $user->id . '.', $request);
        return back()->with('success', 'User profile, credits, and server slots saved.');
    }

    public function deleteUser(Request $request, User $user, PterodactylClient $client): RedirectResponse
    {
        $actor = $request->user();

        if ((int) $actor->id === (int) $user->id) {
            return back()->with('error', 'You cannot delete the account you are currently using.');
        }

        if ($user->is_admin && User::query()->where('is_admin', true)->count() <= 1) {
            return back()->with('error', 'The last administrator account cannot be deleted. Promote another administrator first.');
        }

        $activeServers = ServerInstance::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'suspended'])
            ->count();

        if ($activeServers > 0) {
            return back()->with('error', 'This account still owns ' . $activeServers . ' active or suspended dashboard server(s). Permanently delete those servers first, then delete the account.');
        }

        // Delete the linked Pterodactyl account first. If Pterodactyl refuses
        // (for example, because it still owns servers outside TR Dashboard),
        // keep the local account intact rather than creating an orphan.
        if ($user->ptero_user_id) {
            try {
                $client->deleteUser((int) $user->ptero_user_id);
            } catch (Throwable $e) {
                report($e);
                return back()->with('error', 'Pterodactyl refused to delete the linked account. The dashboard user was not deleted. Check whether the remote account still owns servers and verify Application API permissions.');
            }
        }

        $deletedId = (int) $user->id;
        $deletedUsername = (string) $user->username;
        $deletedEmail = (string) $user->email;
        $wasAdmin = (bool) $user->is_admin;

        ActivityLogger::record(
            $actor->id,
            $actor->username,
            'admin_user_deleted',
            'Deleted dashboard account #' . $deletedId . ' (' . $deletedUsername . ', ' . $deletedEmail . ')' . ($wasAdmin ? ' [administrator]' : '') . '.',
            $request
        );

        try {
            DB::transaction(function () use ($deletedId): void {
                $target = User::query()->whereKey($deletedId)->lockForUpdate()->firstOrFail();
                $target->delete();
            });
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'The remote account was handled, but the local account could not be deleted. Check the database logs before retrying.');
        }

        return redirect()->route('admin.users')->with('success', 'Account ' . $deletedUsername . ' was deleted. Its local tickets, credit history, and old server records were removed according to the database relationships.');
    }

    public function servers(Request $request): View
    {
        $query = ServerInstance::query()->with('user')->latest();
        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(fn ($q) => $q->where('name', 'like', '%' . $search . '%')->orWhereHas('user', fn ($u) => $u->where('username', 'like', '%' . $search . '%')));
        }
        return view('admin.servers', ['servers' => $query->paginate(20)->withQueryString(), 'search' => $search ?? '']);
    }

    public function suspendServer(Request $request, ServerInstance $server, PterodactylClient $client): RedirectResponse
    {
        if ($server->status !== 'active') {
            return back()->with('error', 'Only active servers can be suspended from this action.');
        }
        try {
            $client->suspend((int) $server->ptero_server_id);
            $server->update([
                'status' => 'suspended',
                'suspended_at' => now(),
                'delete_at' => now()->addDays(max(1, (int) Settings::get('server_grace_days', 7))),
            ]);
            ActivityLogger::record($request->user()->id, $request->user()->username, 'admin_server_suspend', 'Suspended server ' . $server->name . '.', $request);
            return back()->with('success', 'Server suspended.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'Pterodactyl could not suspend this server. Check the API settings.');
        }
    }

    public function deleteServer(Request $request, ServerInstance $server, PterodactylClient $client): RedirectResponse
    {
        if ($server->status === 'deleted') {
            return back()->with('error', 'This server has already been deleted.');
        }
        try {
            $client->deleteServer((int) $server->ptero_server_id, true);
            $server->update(['status' => 'deleted', 'deleted_at' => now(), 'delete_at' => null]);
            ActivityLogger::record($request->user()->id, $request->user()->username, 'admin_server_delete', 'Permanently deleted server ' . $server->name . '.', $request);
            return back()->with('success', 'Server permanently deleted from Pterodactyl.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'Pterodactyl could not delete this server. Check the API settings.');
        }
    }

    public function redeemCodes(): View
    {
        return view('admin.redeem-codes', ['codes' => RedeemCode::with('creator')->latest()->paginate(20)]);
    }

    public function createRedeemCodes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'credits' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'slot_bonus' => ['required', 'integer', 'min:0', 'max:10000'],
            'count' => ['required', 'integer', 'min:1', 'max:100'],
            'max_uses' => ['required', 'integer', 'min:1', 'max:100000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        if ((float) $data['credits'] <= 0 && (int) $data['slot_bonus'] <= 0) {
            return back()->withErrors(['credits' => 'A code must award credits, server slots, or both.'])->withInput();
        }

        $created = [];
        for ($i = 0; $i < (int) $data['count']; $i++) {
            do {
                $code = strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4));
            } while (RedeemCode::where('code', $code)->exists());

            RedeemCode::create([
                'code' => $code,
                'credits' => round((float) $data['credits'], 2),
                'slot_bonus' => (int) $data['slot_bonus'],
                'max_uses' => (int) $data['max_uses'],
                'uses' => 0,
                'expires_at' => $data['expires_at'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $created[] = $code;
        }

        ActivityLogger::record($request->user()->id, $request->user()->username, 'redeem_codes_created', 'Created ' . count($created) . ' redeem code(s).', $request);
        return redirect()->route('admin.redeem-codes')->with('created_codes', $created)->with('success', count($created) . ' redeem code(s) created. Copy them now to share with users.');
    }

    public function tickets(Request $request): View
    {
        $query = SupportTicket::query()->with('user')->withCount('replies')->latest();
        if ($status = trim((string) $request->query('status', ''))) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(fn ($q) => $q->where('subject', 'like', '%' . $search . '%')->orWhereHas('user', fn ($u) => $u->where('username', 'like', '%' . $search . '%')));
        }
        return view('admin.tickets', ['tickets' => $query->paginate(20)->withQueryString(), 'statusFilter' => $status ?? '', 'search' => $search ?? '']);
    }

    public function updateTicketStatus(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:open,client_reply,admin_reply,closed']]);
        $ticket->update(['status' => $data['status'], 'closed_at' => $data['status'] === 'closed' ? now() : null]);
        ActivityLogger::record($request->user()->id, $request->user()->username, 'ticket_status_changed', 'Changed ticket #' . $ticket->id . ' status to ' . $data['status'] . '.', $request);
        return back()->with('success', 'Ticket status updated.');
    }

    public function logs(): View
    {
        return view('admin.logs', ['logs' => ActivityLog::with('user')->latest()->paginate(30)]);
    }
}
