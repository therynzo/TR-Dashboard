<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
use App\Models\ServerInstance;
use App\Services\PterodactylClient;
use App\Support\ActivityLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9 _.-]+$/'],
            'location_id' => ['required', 'integer', 'min:1'],
            'node_id' => ['required', 'integer', 'min:1'],
            'nest_id' => ['required', 'integer', 'min:1'],
            'egg_id' => ['required', 'integer', 'min:1'],
            'environment' => ['nullable', 'array'],
            'environment.*' => ['nullable', 'string', 'max:2000'],
        ]);
        $user = $request->user();

        try {
            // Validate every selected value against the live panel; do not trust dropdown IDs from the browser.
            $locationId = (int) $data['location_id'];
            $nodeId = (int) $data['node_id'];
            $nestId = (int) $data['nest_id'];
            $eggId = (int) $data['egg_id'];

            $locations = $pterodactyl->locations();
            $locationExists = collect($locations)->contains(fn ($item) => (int) data_get($item, 'attributes.id', 0) === $locationId);
            if (!$locationExists) {
                throw new RuntimeException('The selected location no longer exists in Pterodactyl. Reload the create-server page and choose again.');
            }

            $nodes = $pterodactyl->nodes($locationId);
            $node = collect($nodes)->first(fn ($item) => (int) data_get($item, 'attributes.id', 0) === $nodeId);
            if (!$node) {
                throw new RuntimeException('The selected node does not belong to that location or is no longer available.');
            }
            if ((bool) data_get($node, 'attributes.maintenance_mode', false)) {
                throw new RuntimeException('The selected node is in maintenance mode. Choose another node.');
            }

            // Automatically select a free allocation on the selected node. The browser does
            // not submit an allocation ID; fresh panel data is checked here at deploy time.
            $freeAllocations = collect($pterodactyl->allocations($nodeId))
                ->filter(fn ($item) => !(bool) data_get($item, 'attributes.assigned', false))
                ->sortBy(fn ($item) => (int) data_get($item, 'attributes.id', PHP_INT_MAX))
                ->values();

            if ($freeAllocations->isEmpty()) {
                throw new RuntimeException('No free allocation is available on this node. Choose another node or ask the administrator to add free allocations in Pterodactyl.');
            }

            $allocationIds = $freeAllocations
                ->map(fn ($item) => (int) data_get($item, 'attributes.id', 0))
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();

            if (!$allocationIds) {
                throw new RuntimeException('Pterodactyl returned free allocations without valid IDs. Check the node allocation configuration.');
            }

            $eggRecord = $pterodactyl->egg($nestId, $eggId);
            $eggAttributes = $eggRecord['attributes'] ?? [];
            $nest = collect($pterodactyl->nests())->first(fn ($item) => (int) data_get($item, 'attributes.id', 0) === $nestId);
            if (!$nest) {
                throw new RuntimeException('The selected software category no longer exists. Reload the page and choose again.');
            }

            $submittedEnvironment = $data['environment'] ?? [];
            $environment = [];
            $variables = data_get($eggRecord, 'relationships.variables.data', []);
            foreach ($variables as $variable) {
                $v = $variable['attributes'] ?? [];
                $key = (string) ($v['env_variable'] ?? '');
                if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                    continue;
                }
                $editable = (bool) ($v['user_editable'] ?? false);
                $value = $editable && array_key_exists($key, $submittedEnvironment)
                    ? (string) $submittedEnvironment[$key]
                    : (string) ($v['default_value'] ?? '');
                $rules = (string) ($v['rules'] ?? 'nullable|string');
                if (str_contains($rules, 'required') && trim($value) === '') {
                    throw new RuntimeException('The required Egg variable "' . $key . '" is empty. Choose another Egg or ask the administrator to configure a default value.');
                }
                if ($editable) {
                    $validator = \Illuminate\Support\Facades\Validator::make([$key => $value], [$key => $rules]);
                    if ($validator->fails()) {
                        throw new RuntimeException('Invalid value for Egg variable "' . $key . '": ' . $validator->errors()->first($key));
                    }
                }
                $environment[$key] = $value;
            }

            $dockerImages = $eggAttributes['docker_images'] ?? [];
            // Pterodactyl returns image-label => Docker-image. Send the image URI value,
            // not its human-readable label, or create-server commonly returns HTTP 422.
            $dockerImage = is_array($dockerImages) && count($dockerImages)
                ? (string) array_values($dockerImages)[0]
                : (string) ($eggAttributes['docker_image'] ?? Settings::get('ptero_docker_image', 'ghcr.io/pterodactyl/yolks:java_21'));

            if ($dockerImage === '' || !str_contains($dockerImage, '/')) {
                $configuredImage = trim((string) Settings::get('ptero_docker_image', ''));
                if ($configuredImage !== '' && str_contains($configuredImage, '/')) {
                    $dockerImage = $configuredImage;
                } else {
                    throw new RuntimeException('The selected Egg has no valid Docker image. Ask the administrator to reload the Egg defaults or configure a valid Docker image.');
                }
            }
            $startup = (string) ($eggAttributes['startup'] ?? Settings::get('ptero_startup', 'java -jar {{SERVER_JARFILE}}'));
            $nestName = (string) data_get($nest, 'attributes.name', 'Software');
            $eggName = (string) ($eggAttributes['name'] ?? 'Egg');

            $server = DB::transaction(function () use ($data, $user, $pterodactyl, $eggId, $allocationIds, $environment, $dockerImage, $startup, $nestName, $eggName) {
                $lockedUser = $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                if (!$lockedUser->ptero_user_id) {
                    throw new RuntimeException('Your dashboard account has not been linked to a Pterodactyl user.');
                }

                $slotUse = ServerInstance::query()->where('user_id', $lockedUser->id)->whereIn('status', ['active', 'suspended'])->count();
                if ($slotUse >= (int) $lockedUser->server_slots) {
                    throw new RuntimeException('You have used all your server slots. Contact an administrator to request another slot.');
                }

                $cost = max(0, (float) Settings::get('server_create_cost', 0));
                if ((float) $lockedUser->credits < $cost) {
                    throw new RuntimeException('Not enough credits to create this server.');
                }

                $memory = max(128, (int) Settings::get('ptero_memory', 2048));
                $disk = max(256, (int) Settings::get('ptero_disk', 5120));
                $cpu = max(1, (int) Settings::get('ptero_cpu', 100));
                $payload = [
                    'name' => $data['name'],
                    'user' => (int) $lockedUser->ptero_user_id,
                    'egg' => $eggId,
                    'docker_image' => $dockerImage,
                    'startup' => $startup,
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
                    'allocation' => ['default' => $allocationIds[0]],
                ];

                $remote = null;
                $lastAllocationError = null;
                foreach ($allocationIds as $candidateAllocationId) {
                    $payload['allocation'] = ['default' => $candidateAllocationId];
                    try {
                        $remote = $pterodactyl->createServer($payload);
                        break;
                    } catch (RequestException $allocationException) {
                        $statusCode = $allocationException->response?->status();
                        $errorText = strtolower((string) $allocationException->response?->body());
                        $isAllocationConflict = $statusCode === 422
                            && (str_contains($errorText, 'allocation')
                                || str_contains($errorText, 'port')
                                || str_contains($errorText, 'default'));
                        if (!$isAllocationConflict) {
                            throw $allocationException;
                        }
                        $lastAllocationError = $allocationException;
                    }
                }

                if (!$remote) {
                    if ($lastAllocationError) {
                        throw $lastAllocationError;
                    }
                    throw new RuntimeException('Could not create the server because no free allocation could be assigned.');
                }
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
                    'software' => $nestName . ' / ' . $eggName,
                    'credits_paid' => $cost,
                    'expires_at' => now()->addHours(max(1, (int) Settings::get('server_lifetime_hours', 48))),
                ]);
            });

            ActivityLogger::record($user->id, $user->username, 'server_created', 'Created server ' . $server->name . ' (Pterodactyl ID ' . $server->ptero_server_id . ').', $request);
            return redirect()->route('servers.show', $server)->with('success', 'Your server has been deployed in Pterodactyl.');
        } catch (Throwable $e) {
            report($e);
            if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response) {
                $status = $e->response->status();
                $message = match ($status) {
                    401 => 'Pterodactyl rejected the Application API key (HTTP 401). Re-save the Application API key in Admin → Pterodactyl API.',
                    403 => 'Pterodactyl refused this action (HTTP 403). Check Application API permissions for server creation and reading users, nodes, Eggs, and allocations.',
                    404 => 'Pterodactyl returned HTTP 404. Check the panel root URL and selected software category and Egg.',
                    422 => 'Pterodactyl rejected the server configuration (HTTP 422).',
                    default => 'Pterodactyl API request failed with HTTP ' . $status . '. See storage/logs/laravel.log for details.',
                };

                if ($status === 422) {
                    $apiErrors = $e->response->json('errors', []);
                    $details = collect(is_array($apiErrors) ? $apiErrors : [])
                        ->map(fn ($error) => trim((string) (data_get($error, 'detail') ?: data_get($error, 'message') ?: data_get($error, 'title'))))
                        ->filter()
                        ->unique()
                        ->take(3)
                        ->implode(' ');
                    if ($details !== '') {
                        $message .= ' Pterodactyl says: ' . mb_substr($details, 0, 700);
                    } else {
                        $message .= ' No validation detail was returned. Check storage/logs/laravel.log for the API response.';
                    }
                }

                return back()->withInput()->with('error', $message);
            }
            return back()->withInput()->with('error', $e instanceof RuntimeException ? $e->getMessage() : 'Server deployment failed. Review the latest Laravel logs for details.');
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
