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
        $apiKey = (string) Settings::get('ptero_api_key', '');

        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('Pterodactyl is not configured yet. Add the panel URL and an Application API key in Admin → Settings.');
        }

        if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new RuntimeException('The Pterodactyl panel URL must be a valid HTTP or HTTPS URL.');
        }

        return Http::withToken($apiKey)
            ->acceptJson()
            ->withHeaders(['Content-Type' => 'application/json', 'User-Agent' => 'TR-Dashboard/1.0'])
            ->baseUrl($baseUrl . '/api/application')
            ->timeout(25)
            ->connectTimeout(10);
    }

    public function testConnection(): array
    {
        $response = $this->request()->get('/users', ['per_page' => 1]);
        $response->throw();
        return ['ok' => true, 'message' => 'Connected to the Pterodactyl Application API.'];
    }

    public function createUser(string $username, string $email, string $password): int
    {
        $response = $this->request()->post('/users', [
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

    public function updateUser(int $userId, array $payload): void
    {
        $this->request()->patch('/users/' . $userId, $payload)->throw();
    }

    public function createServer(array $payload): array
    {
        $response = $this->request()->post('/servers', $payload);
        $response->throw();
        $attributes = $response->json('attributes') ?: $response->json('data.attributes');
        if (!is_array($attributes) || empty($attributes['id'])) {
            throw new RuntimeException('The Pterodactyl API did not return the created server details.');
        }
        return $attributes;
    }

    public function suspend(int $serverId): void
    {
        $this->request()->post('/servers/' . $serverId . '/suspend')->throw();
    }

    public function unsuspend(int $serverId): void
    {
        $this->request()->post('/servers/' . $serverId . '/unsuspend')->throw();
    }

    public function deleteServer(int $serverId, bool $force = true): void
    {
        $url = '/servers/' . $serverId . ($force ? '?force=true' : '');
        $this->request()->delete($url)->throw();
    }
}
