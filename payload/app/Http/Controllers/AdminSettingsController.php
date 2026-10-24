<?php

namespace App\Http\Controllers;

use App\Services\PterodactylClient;
use Illuminate\Http\Client\RequestException;
use App\Support\ActivityLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

class AdminSettingsController extends Controller
{
    public function branding(): View
    {
        return view('admin.settings.branding', ['settings' => $this->values()]);
    }

    public function saveBranding(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:60'],
            'theme_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'announcement' => ['nullable', 'string', 'max:500'],
            'discord_url' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);
        foreach (['brand_name', 'theme_color', 'announcement', 'discord_url'] as $key) {
            Settings::set($key, $data[$key] ?? '');
        }
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            Settings::set('logo_url', Storage::url($path));
        } elseif ($request->boolean('reset_logo')) {
            // Reset any previously uploaded custom logo to the newly bundled user-provided mark.
            Settings::set('logo_url', '/tr-logo.png');
        }
        ActivityLogger::record($request->user()->id, $request->user()->username, 'branding_settings_updated', 'Updated dashboard branding settings.', $request);
        return back()->with('success', 'Branding settings saved.');
    }

    public function pterodactyl(): View
    {
        $settings = $this->values();
        $settings['ptero_api_key_configured'] = Settings::get('ptero_api_key', '') !== '';
        return view('admin.settings.pterodactyl', ['settings' => $settings]);
    }

    public function savePterodactyl(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ptero_url' => ['required', 'url', 'max:255'],
            'ptero_api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $url = rtrim(trim($data['ptero_url']), '/');
        $url = preg_replace('#/api/application$#i', '', $url) ?? $url;
        $url = preg_replace('#/api/client$#i', '', $url) ?? $url;

        $key = trim((string) ($data['ptero_api_key'] ?? ''));
        if ($key === '' && trim((string) Settings::get('ptero_api_key', '')) === '') {
            return back()->withErrors(['ptero_api_key' => 'Enter an Application API key before saving the connection.'])->withInput();
        }

        Settings::set('ptero_url', $url);
        if ($key !== '') {
            Settings::set('ptero_api_key', $key);
        }

        ActivityLogger::record($request->user()->id, $request->user()->username, 'pterodactyl_connection_saved', 'Saved the Pterodactyl panel URL and Application API key.', $request);
        return redirect()->route('admin.settings.pterodactyl')->with('success', 'Panel URL and API key saved. Next, click Test API connection, then Load panel data to select the node, software, and allocation.');
    }

    public function savePterodactylDefaults(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ptero_location_id' => ['required', 'integer', 'min:1'],
            'ptero_node_id' => ['required', 'integer', 'min:1'],
            'ptero_nest_id' => ['required', 'integer', 'min:1'],
            'ptero_egg_id' => ['required', 'integer', 'min:1'],
            'ptero_memory' => ['required', 'integer', 'min:128'],
            'ptero_disk' => ['required', 'integer', 'min:256'],
            'ptero_cpu' => ['required', 'integer', 'min:1', 'max:10000'],
            'ptero_swap' => ['required', 'integer', 'min:-1'],
            'ptero_io' => ['required', 'integer', 'min:10', 'max:1000'],
            'ptero_databases' => ['required', 'integer', 'min:0', 'max:100'],
            'ptero_allocations' => ['required', 'integer', 'min:1', 'max:100'],
            'ptero_backups' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        foreach ($data as $key => $value) {
            Settings::set($key, $value);
        }

        ActivityLogger::record($request->user()->id, $request->user()->username, 'pterodactyl_defaults_saved', 'Saved default Pterodactyl location, node, software, Egg, and server resource limits.', $request);
        return redirect()->route('admin.settings.pterodactyl')->with('success', 'Connection defaults saved. The server creation page will now use live panel options.');
    }

    public function testPterodactyl(Request $request, PterodactylClient $client): RedirectResponse
    {
        try {
            $result = $client->testConnection();
            ActivityLogger::record($request->user()->id, $request->user()->username, 'ptero_test', 'Tested Pterodactyl API connection: success.', $request);
            return back()->with('success', $result['message']);
        } catch (Throwable $e) {
            report($e);
            ActivityLogger::record($request->user()->id, $request->user()->username, 'ptero_test', 'Tested Pterodactyl API connection: failed.', $request);
            if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response) {
                $status = $e->response->status();
                $message = match ($status) {
                    401 => 'Connection failed (HTTP 401): the Application API key was rejected. Create a Pterodactyl Application API key (ptla_...) and save it again.',
                    403 => 'Connection failed (HTTP 403): the API key is valid but lacks permission to read Application API users/nodes. Check key permissions.',
                    404 => 'Connection failed (HTTP 404): check that the URL is the panel root URL, e.g. https://panel.example.com, not /api/client.',
                    429 => 'Connection failed (HTTP 429): Pterodactyl rate-limited the request. Wait and retry.',
                    default => 'Pterodactyl API returned HTTP ' . $status . '. Check the Pterodactyl panel log for details.',
                };
                return back()->with('error', $message);
            }
            $message = mb_substr($e->getMessage() ?: 'Could not connect to Pterodactyl.', 0, 240);
            return back()->with('error', 'Pterodactyl connection failed: ' . $message);
        }
    }

    public function defaults(): View
    {
        return view('admin.settings.defaults', ['settings' => $this->values()]);
    }

    public function saveDefaults(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_credits' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'default_server_slots' => ['required', 'integer', 'min:0', 'max:10000'],
            'server_create_cost' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'server_renew_cost' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'server_lifetime_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'server_grace_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);
        foreach ($data as $key => $value) {
            Settings::set($key, $value);
        }
        ActivityLogger::record($request->user()->id, $request->user()->username, 'server_defaults_updated', 'Updated default credits, slots, costs, and server lifecycle settings.', $request);
        return back()->with('success', 'Credit and server defaults saved. Existing user balances and current server expiry dates were not changed.');
    }

    public function authentication(): View
    {
        $settings = $this->values();
        foreach (['discord', 'google'] as $provider) {
            $settings[$provider . '_oauth_secret_configured'] = Settings::get($provider . '_oauth_secret', '') !== '';
            $settings[$provider . '_callback_url'] = rtrim((string) config('app.url'), '/') . route('oauth.callback', ['provider' => $provider], false);
        }
        return view('admin.settings.authentication', ['settings' => $settings]);
    }

    public function saveAuthentication(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'discord_oauth_client_id' => ['nullable', 'string', 'max:255'],
            'discord_oauth_secret' => ['nullable', 'string', 'max:1000'],
            'google_oauth_client_id' => ['nullable', 'string', 'max:255'],
            'google_oauth_secret' => ['nullable', 'string', 'max:1000'],
        ]);
        foreach (['discord', 'google'] as $provider) {
            $enabled = $request->boolean($provider . '_oauth_enabled');
            $clientId = trim((string) ($data[$provider . '_oauth_client_id'] ?? ''));
            if ($clientId !== '') {
                Settings::set($provider . '_oauth_client_id', $clientId);
            }
            $secretKey = $provider . '_oauth_secret';
            $secret = trim((string) ($data[$secretKey] ?? ''));
            if ($secret !== '') {
                Settings::set($secretKey, $secret);
            }
            $effectiveId = trim((string) Settings::get($provider . '_oauth_client_id', ''));
            $effectiveSecret = trim((string) Settings::get($secretKey, ''));
            if ($enabled && ($effectiveId === '' || $effectiveSecret === '')) {
                return back()->withErrors([$provider . '_oauth_client_id' => ucfirst($provider) . ' login cannot be enabled until both the Client ID and Client Secret have been saved.'])->withInput();
            }
            Settings::set($provider . '_oauth_enabled', $enabled ? '1' : '0');
        }
        ActivityLogger::record($request->user()->id, $request->user()->username, 'oauth_settings_updated', 'Updated optional Discord and Google sign-in settings.', $request);
        return back()->with('success', 'Authentication settings saved. Providers remain off unless their toggle is enabled.');
    }

    public function linkvertise(): View
    {
        $settings = $this->values();
        $settings['linkvertise_token_configured'] = Settings::get('linkvertise_anti_bypass_token', '') !== '';
        $settings['linkvertise_callback_url'] = rtrim((string) config('app.url'), '/') . route('linkvertise.complete', [], false);
        return view('admin.settings.linkvertise', ['settings' => $settings]);
    }

    public function saveLinkvertise(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'linkvertise_publisher_id' => ['nullable', 'string', 'max:32', 'regex:/^[0-9]*$/'],
            'linkvertise_url' => ['nullable', 'url', 'max:1000'],
            'linkvertise_anti_bypass_token' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
            'linkvertise_reward_credits' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'linkvertise_daily_limit' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        if (!empty($data['linkvertise_url']) && !str_starts_with(strtolower($data['linkvertise_url']), 'https://')) {
            return back()->withErrors(['linkvertise_url' => 'Use an HTTPS Linkvertise URL.'])->withInput();
        }
        $token = trim((string) ($data['linkvertise_anti_bypass_token'] ?? ''));
        if ($token !== '') {
            Settings::set('linkvertise_anti_bypass_token', $token);
        }
        foreach (['linkvertise_publisher_id', 'linkvertise_url', 'linkvertise_reward_credits', 'linkvertise_daily_limit'] as $key) {
            Settings::set($key, $data[$key] ?? '');
        }
        $enabled = $request->boolean('linkvertise_enabled');
        if ($enabled && (trim((string) Settings::get('linkvertise_url', '')) === '' || trim((string) Settings::get('linkvertise_anti_bypass_token', '')) === '')) {
            Settings::set('linkvertise_enabled', '0');
            return back()->withErrors(['linkvertise_url' => 'Before enabling rewards, save a Linkvertise HTTPS target URL and a valid 64-character hexadecimal anti-bypass token.'])->withInput();
        }
        Settings::set('linkvertise_enabled', $enabled ? '1' : '0');
        ActivityLogger::record($request->user()->id, $request->user()->username, 'linkvertise_settings_updated', 'Updated optional Linkvertise reward settings.', $request);
        return back()->with('success', 'Linkvertise settings saved. Reward claims are verified server-side before credits are added.');
    }

    /**
     * Check Linkvertise anti-bypass token validity without awarding credits.
     * A fresh random hash intentionally produces FALSE when the token is valid.
     */
    public function testLinkvertise(Request $request): RedirectResponse
    {
        $submittedToken = trim((string) $request->input('linkvertise_anti_bypass_token', ''));
        $token = $submittedToken !== ''
            ? $submittedToken
            : trim((string) Settings::get('linkvertise_anti_bypass_token', ''));

        if ($token === '') {
            return back()->with('error', 'No anti-bypass token was provided or saved. Enter the token from Linkvertise Settings → Anti-Bypassing first.');
        }
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $token)) {
            return back()->with('error', 'The anti-bypass token must be exactly 64 hexadecimal characters. Copy it again from Linkvertise and retry.');
        }

        try {
            // The random hash is deliberately not associated with a user task. It must never pay credits.
            $randomHash = bin2hex(random_bytes(32));
            $verifyUrl = 'https://publisher.linkvertise.com/api/v1/anti_bypassing?' . http_build_query([
                'token' => $token,
                'hash' => $randomHash,
            ], '', '&', PHP_QUERY_RFC3986);
            $response = Http::connectTimeout(3)->timeout(8)->post($verifyUrl);
            $body = strtoupper(trim(trim($response->body()), " \\t\\n\\r\\0\\x0B\\\"'"));

            if (!$response->successful()) {
                return back()->with('error', 'Linkvertise API returned HTTP ' . $response->status() . '. The token could not be confirmed. This can be a provider/network issue; retry shortly. No credits were awarded.');
            }

            if ($body === 'FALSE') {
                return back()->with('success', 'Linkvertise API is reachable and the anti-bypass token is accepted. FALSE is expected because this test uses a random hash. No credits were awarded.');
            }

            if (str_contains($body, 'WRONG_TOKEN') || str_contains($body, 'INVALID AUTHENTICATION TOKEN') || str_contains($body, 'TOKEN NOT VALID')) {
                return back()->with('error', 'Linkvertise rejected the anti-bypass token. Copy the current token from Linkvertise Settings → Anti-Bypassing, save it there, then replace it here and test again.');
            }

            return back()->with('error', 'Linkvertise responded with an unexpected verification result (' . mb_substr($body ?: 'empty response', 0, 50) . '). The token could not be confirmed; no credits were awarded.');
        } catch (Throwable $e) {
            // Do not log exception text because the request URL contains the secret token.
            logger()->warning('Linkvertise token test could not reach the verification service.', [
                'exception_class' => get_class($e),
            ]);
            return back()->with('error', 'Could not reach Linkvertise verification API (connection, DNS, TLS, timeout, or provider issue). This test awarded no credits. Retry shortly.');
        }
    }

    private function values(): array
    {
        $settings = Settings::defaults();
        foreach (array_keys($settings) as $key) {
            $settings[$key] = Settings::get($key, $settings[$key]);
        }
        return $settings;
    }
}
