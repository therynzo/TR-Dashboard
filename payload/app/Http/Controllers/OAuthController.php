<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\PterodactylClient;
use App\Support\ActivityLogger;
use App\Support\Settings;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class OAuthController extends Controller
{
    public function redirect(string $provider, Request $request): RedirectResponse
    {
        if (!in_array($provider, ['discord', 'google'], true)) {
            abort(404);
        }
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        try {
            $this->assertConfigured($provider);
        } catch (\RuntimeException $e) {
            return redirect()->route('login')->with('error', $e->getMessage());
        }
        $state = Str::random(48);
        $request->session()->put('tr_oauth_state_' . $provider, $state);
        $callback = $this->callbackUrl($provider);
        $clientId = (string) Settings::get($provider . '_oauth_client_id', '');

        if ($provider === 'discord') {
            $query = [
                'client_id' => $clientId,
                'redirect_uri' => $callback,
                'response_type' => 'code',
                'scope' => 'identify email',
                'state' => $state,
                'prompt' => 'consent',
            ];
            return redirect('https://discord.com/oauth2/authorize?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        }

        $query = [
            'client_id' => $clientId,
            'redirect_uri' => $callback,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ];
        return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    public function callback(string $provider, Request $request, PterodactylClient $pterodactyl): RedirectResponse
    {
        if (!in_array($provider, ['discord', 'google'], true)) {
            abort(404);
        }
        try {
            $this->assertConfigured($provider);
            if ($request->filled('error')) {
                throw new \RuntimeException('The provider cancelled authentication.');
            }
            $expectedState = (string) $request->session()->pull('tr_oauth_state_' . $provider, '');
            $actualState = (string) $request->query('state', '');
            if ($expectedState === '' || $actualState === '' || !hash_equals($expectedState, $actualState)) {
                throw new \RuntimeException('OAuth state validation failed. Please start login again.');
            }
            $code = (string) $request->query('code', '');
            if ($code === '') {
                throw new \RuntimeException('The identity provider did not return an authorization code.');
            }

            $callbackUrl = $this->callbackUrl($provider);
            $clientId = (string) Settings::get($provider . '_oauth_client_id', '');
            $clientSecret = (string) Settings::get($provider . '_oauth_secret', '');
            $tokenUrl = $provider === 'discord' ? 'https://discord.com/api/oauth2/token' : 'https://oauth2.googleapis.com/token';
            $tokenResponse = Http::asForm()->acceptJson()->timeout(12)->post($tokenUrl, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $callbackUrl,
            ])->throw()->json();
            $accessToken = (string) ($tokenResponse['access_token'] ?? '');
            if ($accessToken === '') {
                throw new \RuntimeException('The identity provider did not return an access token.');
            }

            $profileUrl = $provider === 'discord'
                ? 'https://discord.com/api/users/@me'
                : 'https://openidconnect.googleapis.com/v1/userinfo';
            $profile = Http::withToken($accessToken)->acceptJson()->timeout(12)->get($profileUrl)->throw()->json();

            $providerId = (string) ($profile['id'] ?? $profile['sub'] ?? '');
            $email = strtolower(trim((string) ($profile['email'] ?? '')));
            $emailVerified = $provider === 'discord'
                ? filter_var($profile['verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
                : filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($providerId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$emailVerified) {
                throw new \RuntimeException('The provider must return a verified email address. Verify your provider email and try again.');
            }

            $providerColumn = $provider . '_oauth_id';
            $user = User::query()->where($providerColumn, $providerId)->first();
            if (!$user) {
                $user = User::query()->where('email', $email)->first();
                if ($user && $user->{$providerColumn} && $user->{$providerColumn} !== $providerId) {
                    throw new \RuntimeException('This email is already linked to a different identity. Sign in using the original method or ask an administrator for help.');
                }
            }

            if (!$user) {
                $display = (string) ($profile['global_name'] ?? $profile['name'] ?? $profile['username'] ?? strstr($email, '@', true) ?? 'user');
                $username = $this->uniqueUsername($display, $email);
                $pteroUserId = null;
                if (trim((string) Settings::get('ptero_url', '')) !== '' && trim((string) Settings::get('ptero_api_key', '')) !== '') {
                    try {
                        // OAuth users have no dashboard password to share; create a separate random Pterodactyl password.
                        $pteroUserId = $pterodactyl->createUser($username, $email, Str::random(48));
                    } catch (Throwable $e) {
                        report($e);
                        // Keep OAuth sign-up available; server deployment will remain unavailable until the account is linked.
                    }
                }

                $user = DB::transaction(function () use ($username, $email, $pteroUserId, $providerColumn, $providerId) {
                    $credits = (float) Settings::get('default_credits', 100);
                    $user = User::create([
                        'name' => $username,
                        'username' => $username,
                        'email' => $email,
                        'password' => Hash::make(Str::random(64)),
                        'local_password_set' => false,
                        'is_admin' => false,
                        'credits' => $credits,
                        'server_slots' => max(0, (int) Settings::get('default_server_slots', 1)),
                        'ptero_user_id' => $pteroUserId,
                        $providerColumn => $providerId,
                    ]);
                    if ($credits > 0) {
                        CreditTransaction::create([
                            'user_id' => $user->id,
                            'amount' => $credits,
                            'balance_after' => $credits,
                            'type' => 'welcome',
                            'description' => 'Welcome credits',
                        ]);
                    }
                    return $user;
                });
                ActivityLogger::record($user->id, $user->username, 'registered_oauth', 'Registered using ' . ucfirst($provider) . ' OAuth.', $request);
            } elseif (!$user->{$providerColumn}) {
                $user->{$providerColumn} = $providerId;
                $user->save();
            }

            Auth::login($user, true);
            $request->session()->regenerate();
            ActivityLogger::record($user->id, $user->username, 'login_oauth', 'Signed in using ' . ucfirst($provider) . ' OAuth.', $request);
            return redirect()->route('dashboard')->with('success', 'Signed in with ' . ucfirst($provider) . '.');
        } catch (Throwable $e) {
            // Avoid logging request bodies that may contain authorization codes or tokens.
            logger()->warning('OAuth sign-in flow failed.', [
                'provider' => $provider,
                'exception_class' => get_class($e),
            ]);
            $message = $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Could not complete ' . ucfirst($provider) . ' sign-in. Check the OAuth credentials and callback URL in Admin → Authentication, then try again.';
            return redirect()->route('login')->with('error', $message);
        }
    }

    private function callbackUrl(string $provider): string
    {
        return rtrim((string) config('app.url'), '/') . route('oauth.callback', ['provider' => $provider], false);
    }

    private function assertConfigured(string $provider): void
    {
        if (!Settings::enabled($provider . '_oauth_enabled')
            || trim((string) Settings::get($provider . '_oauth_client_id', '')) === ''
            || trim((string) Settings::get($provider . '_oauth_secret', '')) === '') {
            throw new \RuntimeException(ucfirst($provider) . ' sign-in is disabled or not fully configured.');
        }
    }

    private function uniqueUsername(string $display, string $email): string
    {
        $base = $display ?: (string) strstr($email, '@', true);
        $base = preg_replace('/[^A-Za-z0-9_]/', '_', $base) ?: 'user';
        $base = trim($base, '_');
        if (strlen($base) < 3) {
            $base = 'user_' . $base;
        }
        $base = substr($base, 0, 25);
        $candidate = $base;
        while (User::where('username', $candidate)->exists()) {
            $candidate = substr($base, 0, 25) . '_' . Str::lower(Str::random(4));
        }
        return $candidate;
    }
}
