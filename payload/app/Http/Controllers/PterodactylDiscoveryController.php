<?php

namespace App\Http\Controllers;

use App\Services\PterodactylClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class PterodactylDiscoveryController extends Controller
{
    public function __invoke(Request $request, PterodactylClient $client, string $resource): JsonResponse
    {
        try {
            $items = match ($resource) {
                'locations' => $client->locations(),
                'nodes' => $client->nodes($request->filled('location_id') ? (int) $request->query('location_id') : null),
                'nests' => $client->nests(),
                'eggs' => $client->eggs((int) $request->query('nest_id', 0)),
                'allocations' => $client->allocations((int) $request->query('node_id', 0)),
                default => throw new RuntimeException('Unsupported Pterodactyl discovery resource.'),
            };

            $data = array_map(function (array $item) use ($resource): array {
                $a = $item['attributes'] ?? [];
                $id = (int) ($a['id'] ?? 0);
                $name = (string) ($a['name'] ?? $a['short'] ?? ('Item #' . $id));

                return match ($resource) {
                    'locations' => [
                        'id' => $id,
                        'name' => trim((string) ($a['short'] ?? $name) . (!empty($a['long']) ? ' — ' . $a['long'] : '')),
                        'short' => (string) ($a['short'] ?? ''),
                        'long' => (string) ($a['long'] ?? ''),
                    ],
                    'nodes' => [
                        'id' => $id,
                        'name' => $name,
                        'location_id' => (int) ($a['location_id'] ?? data_get($item, 'relationships.location.data.attributes.id', 0)),
                        'maintenance_mode' => (bool) ($a['maintenance_mode'] ?? false),
                        'is_public' => (bool) ($a['public'] ?? true),
                    ],
                    'nests' => [
                        'id' => $id,
                        'name' => $name,
                        'description' => (string) ($a['description'] ?? ''),
                    ],
                    'eggs' => [
                        'id' => $id,
                        'name' => $name,
                        'description' => (string) ($a['description'] ?? ''),
                        'startup' => (string) ($a['startup'] ?? data_get($a, 'config.startup', '')),
                        'docker_images' => is_array($a['docker_images'] ?? null) ? $a['docker_images'] : [],
                        'variables' => array_map(static function (array $variable): array {
                            $v = $variable['attributes'] ?? [];
                            return [
                                'name' => (string) ($v['name'] ?? $v['env_variable'] ?? ''),
                                'env_variable' => (string) ($v['env_variable'] ?? ''),
                                'default_value' => (string) ($v['default_value'] ?? ''),
                                'description' => (string) ($v['description'] ?? ''),
                                'user_viewable' => (bool) ($v['user_viewable'] ?? false),
                                'user_editable' => (bool) ($v['user_editable'] ?? false),
                                'rules' => (string) ($v['rules'] ?? ''),
                            ];
                        }, data_get($item, 'relationships.variables.data', data_get($item, 'attributes.relationships.variables.data', []))),
                    ],
                    'allocations' => [
                        'id' => $id,
                        'ip' => (string) ($a['ip'] ?? ''),
                        'ip_alias' => (string) ($a['ip_alias'] ?? ''),
                        'port' => (int) ($a['port'] ?? 0),
                        'assigned' => (bool) ($a['assigned'] ?? false),
                        'notes' => (string) ($a['notes'] ?? ''),
                    ],
                    default => [],
                };
            }, $items);

            return response()->json(['ok' => true, 'resource' => $resource, 'data' => $data]);
        } catch (Throwable $e) {
            report($e);
            $status = 502;
            $message = $e->getMessage();

            if ($e instanceof RequestException && $e->response) {
                $status = $e->response->status();
                $message = match ($status) {
                    401 => 'Pterodactyl rejected the API key (HTTP 401). Check that it is an Application API key and was copied correctly.',
                    403 => 'The API key lacks permission for this resource (HTTP 403). Review the key permissions in Pterodactyl.',
                    404 => 'Pterodactyl returned HTTP 404. Check the panel base URL and make sure it is not the Client API URL.',
                    429 => 'Pterodactyl rate-limited this request (HTTP 429). Wait briefly and retry.',
                    default => 'Pterodactyl API returned HTTP ' . $status . '. Check the panel logs for details.',
                };
            } elseif (str_contains(strtolower($message), 'not configured')) {
                $status = 422;
            } else {
                $message = mb_substr($message ?: 'Could not load Pterodactyl data.', 0, 240);
            }

            return response()->json(['ok' => false, 'message' => $message], $status);
        }
    }
}
