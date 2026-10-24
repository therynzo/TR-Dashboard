<?php

namespace App\Services;

use App\Support\Settings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PterodactylClient
{
    protected function request(): PendingRequest
    {
        $baseUrl = rtrim((string) Settings::get('ptero_url', ''), '/');
        $apiKey = trim((string) Settings::get('ptero_api_key', ''));

        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('Pterodactyl is not configured. Save the panel URL and Application API key first.');
        }
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new RuntimeException('The Pterodactyl panel URL must be a valid HTTP or HTTPS URL.');
        }

        // Keep the trailing slash and use relative endpoint paths below. A leading
        // slash would discard /api/application when resolving the request URI.
        return Http::withToken($apiKey)
            ->acceptJson()
            ->withHeaders(['Content-Type' => 'application/json', 'User-Agent' => 'TR-Dashboard/1.1'])
            ->baseUrl($baseUrl . '/api/application/')
            ->timeout(25)
            ->connectTimeout(10);
    }

    public function testConnection(): array
    {
        $response = $this->request()->get('users', ['per_page' => 1]);
        $response->throw();
        return ['ok' => true, 'message' => 'Connected to the Pterodactyl Application API.'];
    }

    /** Return Application API records, preserving attributes and relationships. */
    private function listRecords(string $endpoint, array $query = []): array
    {
        $records = [];
        $page = 1;
        $lastPage = 1;
        do {
            $response = $this->request()->get($endpoint, array_merge($query, ['per_page' => 100, 'page' => $page]));
            $response->throw();
            $body = $response->json();
            $records = array_merge($records, is_array($body['data'] ?? null) ? $body['data'] : []);
            $lastPage = (int) data_get($body, 'meta.pagination.total_pages', 1);
            $page++;
        } while ($page <= $lastPage && $page <= 25);

        return $records;
    }

    public function locations(): array
    {
        return $this->listRecords('locations');
    }

    public function nodes(?int $locationId = null): array
    {
        $nodes = $this->listRecords('nodes');
        if (!$locationId) {
            return $nodes;
        }

        return array_values(array_filter($nodes, static function (array $item) use ($locationId): bool {
            $attributes = $item['attributes'] ?? [];
            $linkedLocation = (int) ($attributes['location_id'] ?? data_get($item, 'relationships.location.data.attributes.id', 0));
            return $linkedLocation === $locationId;
        }));
    }

    public function nests(): array
    {
        return $this->listRecords('nests');
    }

    public function eggs(int $nestId): array
    {
        if ($nestId < 1) {
            throw new RuntimeException('Select a valid Pterodactyl Nest/software category first.');
        }
        return $this->listRecords('nests/' . $nestId . '/eggs', ['include' => 'variables']);
    }

    public function allocations(int $nodeId): array
    {
        if ($nodeId < 1) {
            throw new RuntimeException('Select a valid Pterodactyl node first.');
        }
        return $this->listRecords('nodes/' . $nodeId . '/allocations');
    }

    public function egg(int $nestId, int $eggId): array
    {
        foreach ($this->eggs($nestId) as $record) {
            if ((int) data_get($record, 'attributes.id', 0) === $eggId) {
                return $record;
            }
        }
        throw new RuntimeException('The selected Egg was not found in the selected software category.');
    }

    public function createUser(string $username, string $email, string $password): int
    {
        $response = $this->request()->post('users', [
            'email' => $email,
            'username' => $username,
            'first_name' => $username,
            'last_name' => 'User',
            'password' => $password,
            'root_admin' => false,
        ]);
        $response->throw();
        $id = (int) $response->json('attributes.id', $response->json('data.attributes.id', 0));
        if ($id < 1) {
            throw new RuntimeException('The Pterodactyl API response did not include a user ID.');
        }
        return $id;
    }

    public function deleteUser(int $userId): void
    {
        if ($userId < 1) {
            throw new RuntimeException('Invalid Pterodactyl user ID.');
        }
        $this->request()->delete('users/' . $userId)->throw();
    }

    public function updateUser(int $userId, array $payload): void
    {
        $this->request()->patch('users/' . $userId, $payload)->throw();
    }

    public function createServer(array $payload): array
    {
        $response = $this->request()->post('servers', $payload);
        $response->throw();
        $attributes = $response->json('attributes') ?: $response->json('data.attributes');
        if (!is_array($attributes) || empty($attributes['id'])) {
            throw new RuntimeException('The Pterodactyl API did not return the created server details.');
        }
        return $attributes;
    }

    public function suspend(int $serverId): void
    {
        $this->request()->post('servers/' . $serverId . '/suspend')->throw();
    }

    public function unsuspend(int $serverId): void
    {
        $this->request()->post('servers/' . $serverId . '/unsuspend')->throw();
    }

    public function deleteServer(int $serverId, bool $force = true): void
    {
        $url = 'servers/' . $serverId . ($force ? '?force=true' : '');
        $this->request()->delete($url)->throw();
    }
}
