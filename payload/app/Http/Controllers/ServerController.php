<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
use App\Models\ServerInstance;
use App\Services\PterodactylClient;
use App\Support\ActivityLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ServerController extends Controller
{
    public function index(Request $request): View
    {
        return view('servers.index', ['servers' => $request->user()->servers()->latest()->paginate(10)]);
    }

    public function createForm(Request $request): View|RedirectResponse
    {
        if (!$request->user()->ptero_user_id) {
            return redirect()->route('servers.index')->with('error', 'Your dashboard account is not linked to a Pterodactyl user yet. Contact the administrator.');
        }
        return view('servers.create');
    }

    public function store(Request $request, PterodactylClient $pterodactyl): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9 _.-]+$/']]);
        $user = $request->user();

        try {
            $server = DB::transaction(function () use ($data, $user, $pterodactyl) {
                $lockedUser = $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                if (!$lockedUser->ptero_user_id) {
                    throw new RuntimeException('Your user account has not been linked to Pterodactyl.');
                }

                $slotUse = ServerInstance::query()->where('user_id', $lockedUser->id)->whereIn('status', ['active', 'suspended'])->count();
                if ($slotUse >= (int) $lockedUser->server_slots) {
                    throw new RuntimeException('You have used all your server slots. Contact an administrator to request another slot.');
                }

                $cost = max(0, (float) Settings::get('server_create_cost', 0));
                if ((float) $lockedUser->credits < $cost) {
                    throw new RuntimeException('Not enough credits to create this server.');
                }

                $egg = (int) Settings::get('ptero_egg_id', 0);
                $allocation = (int) Settings::get('ptero_allocation_id', 0);
                if ($egg < 1 || $allocation < 1) {
                    throw new RuntimeException('The administrator must configure the default Pterodactyl Egg ID and Allocation ID in Admin → Settings first.');
                }

                $environment = json_decode((string) Settings::get('ptero_environment', '{}'), true);
                if (!is_array($environment)) {
                    throw new RuntimeException('The configured Pterodactyl environment must be valid JSON.');
                }
                $memory = max(128, (int) Settings::get('ptero_memory', 2048));
                $disk = max(256, (int) Settings::get('ptero_disk', 5120));
                $cpu = max(1, (int) Settings::get('ptero_cpu', 100));
                $payload = [
                    'name' => $data['name'],
                    'user' => (int) $lockedUser->ptero_user_id,
                    'egg' => $egg,
                    'docker_image' => (string) Settings::get('ptero_docker_image', 'ghcr.io/pterodactyl/yolks:java_21'),
                    'startup' => (string) Settings::get('ptero_startup', 'java -jar {{SERVER_JARFILE}}'),
                    'environment' => $environment,
                    'limits' => [
                        'memory' => $memory,
                        'swap' => (int) Settings::get('ptero_swap', 0),
                        'disk' => $disk,
                        'io' => (int) Settings::get('ptero_io', 500),
                        'cpu' => $cpu,
                    ],
                    'feature_limits' => [
                        'databases' => (int) Settings::get('ptero_databases', 1),
                        'allocations' => (int) Settings::get('ptero_allocations', 1),
                        'backups' => (int) Settings::get('ptero_backups', 1),
                    ],
                    'allocation' => ['default' => $allocation],
                ];

                $remote = $pterodactyl->createServer($payload);
                $lockedUser->credits = round((float) $lockedUser->credits - $cost, 2);
                $lockedUser->save();
                if ($cost > 0) {
                    CreditTransaction::create([
                        'user_id' => $lockedUser->id,
                        'amount' => -$cost,
                        'balance_after' => $lockedUser->credits,
                        'type' => 'server_create',
                        'description' => 'Created server: ' . $data['name'],
                    ]);
                }

                return ServerInstance::create([
                    'user_id' => $lockedUser->id,
                    'name' => $data['name'],
                    'ptero_server_id' => (int) $remote['id'],
                    'ptero_identifier' => $remote['identifier'] ?? null,
                    'status' => 'active',
                    'software' => 'Configured default egg',
                    'credits_paid' => $cost,
                    'expires_at' => now()->addHours(max(1, (int) Settings::get('server_lifetime_hours', 48))),
                ]);
            });

            ActivityLogger::record($user->id, $user->username, 'server_created', 'Created server ' . $server->name . ' (Pterodactyl ID ' . $server->ptero_server_id . ').', $request);
            return redirect()->route('servers.show', $server)->with('success', 'Your server has been deployed in Pterodactyl.');
        } catch (Throwable $e) {
            report($e);
            return back()->withInput()->with('error', $e instanceof RuntimeException ? $e->getMessage() : 'Server deployment failed. Check the Pterodactyl URL, API key, Egg, Allocation, and application logs.');
        }
    }

    public function show(Request $request, ServerInstance $server): View
    {
        abort_unless($server->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        return view('servers.show', ['server' => $server]);
    }

    public function renew(Request $request, ServerInstance $server, PterodactylClient $pterodactyl): RedirectResponse
    {
        abort_unless($server->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        if (!$server->isRenewable()) {
            return back()->with('error', 'This server has been permanently deleted and can no longer be renewed.');
        }
        if ($server->delete_at && $server->delete_at->isPast()) {
            return back()->with('error', 'The renewal grace period has ended. This server is queued for permanent deletion and can no longer be renewed.');
        }

        $user = $request->user();
        $cost = max(0, (float) Settings::get('server_renew_cost', 0));
        if ((float) $user->credits < $cost) {
            return back()->with('error', 'Not enough credits to renew this server.');
        }

        try {
            DB::transaction(function () use ($server, $user, $cost, $pterodactyl) {
                $lockedServer = ServerInstance::query()->whereKey($server->id)->lockForUpdate()->firstOrFail();
                $lockedUser = $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                if (!$lockedServer->isRenewable() || ($lockedServer->delete_at && $lockedServer->delete_at->isPast())) {
                    throw new RuntimeException('This server is no longer renewable.');
                }
                if ((float) $lockedUser->credits < $cost) {
                    throw new RuntimeException('Not enough credits to renew this server.');
                }
                if ($lockedServer->status === 'suspended') {
                    $pterodactyl->unsuspend((int) $lockedServer->ptero_server_id);
                }
                $lockedUser->credits = round((float) $lockedUser->credits - $cost, 2);
                $lockedUser->save();
                if ($cost > 0) {
                    CreditTransaction::create([
                        'user_id' => $lockedUser->id,
                        'amount' => -$cost,
                        'balance_after' => $lockedUser->credits,
                        'type' => 'server_renew',
                        'description' => 'Renewed server: ' . $lockedServer->name,
                    ]);
                }
                $lockedServer->status = 'active';
                $renewBase = $lockedServer->expires_at && $lockedServer->expires_at->isFuture() ? $lockedServer->expires_at->copy() : now();
                $lockedServer->expires_at = $renewBase->addHours(max(1, (int) Settings::get('server_lifetime_hours', 48)));
                $lockedServer->suspended_at = null;
                $lockedServer->delete_at = null;
                $lockedServer->save();
            });

            ActivityLogger::record($user->id, $user->username, 'server_renewed', 'Renewed server ' . $server->name . '.', $request);
            return back()->with('success', 'Server renewed successfully.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', $e instanceof RuntimeException ? $e->getMessage() : 'Could not renew the server. Check the Pterodactyl integration.');
        }
    }
}
