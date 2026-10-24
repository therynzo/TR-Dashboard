<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\PterodactylClient;
use App\Support\ActivityLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Client\RequestException;
use RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function loginForm(): View
    {
        return view('auth.login', ['oauthProviders' => $this->oauthProviders()]);
    }

    public function registerForm(): View
    {
        return view('auth.register', ['oauthProviders' => $this->oauthProviders()]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identity' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $data['identity'])
            ->orWhere('username', $data['identity'])
            ->first();

        if (!$user || !Auth::attempt(['email' => $user->email, 'password' => $data['password']], $request->boolean('remember'))) {
            return back()->withErrors(['identity' => 'Those login details do not match our records.'])->onlyInput('identity');
        }

        $request->session()->regenerate();
        ActivityLogger::record($user->id, $user->username, 'login', 'Signed in to TR Dashboard.', $request);
        return redirect()->intended(route('dashboard'))->with('success', 'Welcome back, ' . $user->username . '.');
    }

    public function register(Request $request, PterodactylClient $pterodactyl): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9_]+$/', 'unique:users,username'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms' => ['accepted'],
        ]);

        $pteroUrl = trim((string) Settings::get('ptero_url', ''));
        $pteroKey = trim((string) Settings::get('ptero_api_key', ''));
        $pteroUserId = null;

        // The password is sent directly to Pterodactyl only during this request. It is never stored in plaintext.
        if ($pteroUrl !== '' || $pteroKey !== '') {
            if ($pteroUrl === '' || $pteroKey === '') {
                return back()->withErrors(['email' => 'Pterodactyl integration is only partly configured. Ask the administrator to finish Admin → Settings before registering.'])->withInput($request->except('password', 'password_confirmation'));
            }
            try {
                $pteroUserId = $pterodactyl->createUser($data['username'], $data['email'], $data['password']);
            } catch (RequestException $e) {
                report($e);
                $status = $e->response?->status() ?? 0;
                $apiErrors = $e->response?->json('errors', []);
                $details = collect(is_array($apiErrors) ? $apiErrors : [])
                    ->map(fn ($error) => trim((string) (data_get($error, 'detail') ?: data_get($error, 'message') ?: data_get($error, 'title'))))
                    ->filter()
                    ->unique()
                    ->take(3)
                    ->implode(' ');
                $lowerDetails = strtolower($details);

                if ($status === 401) {
                    $message = 'Pterodactyl rejected the Application API key (HTTP 401). Ask the administrator to save a valid key.';
                } elseif ($status === 403) {
                    $message = 'The Pterodactyl API key cannot create users (HTTP 403). Ask the administrator to grant Users → Create permission to the Application API key.';
                } elseif ($status === 422 && (str_contains($lowerDetails, 'already been taken') || str_contains($lowerDetails, 'already exists') || str_contains($lowerDetails, 'has already been registered'))) {
                    $message = 'That username or email is already registered in Pterodactyl. Use a different username/email or ask the administrator to reconcile the existing Pterodactyl account.';
                } elseif ($status === 422) {
                    $message = 'Pterodactyl rejected the account details (HTTP 422).' . ($details !== '' ? ' ' . mb_substr($details, 0, 500) : ' Check the username, email, and required user fields.');
                } elseif ($status === 429) {
                    $message = 'Pterodactyl is rate-limiting account creation. Wait a few minutes and try again.';
                } elseif ($status >= 500) {
                    $message = 'The Pterodactyl panel returned a server error (HTTP ' . $status . '). Please try again later.';
                } else {
                    $message = 'Pterodactyl account creation failed' . ($status ? ' (HTTP ' . $status . ')' : '') . '.'
                        . ($details !== '' ? ' ' . mb_substr($details, 0, 500) : ' Ask the administrator to check the panel URL and Application API key.');
                }

                return back()->withErrors(['email' => $message])->withInput($request->except('password', 'password_confirmation'));
            } catch (Throwable $e) {
                report($e);
                $message = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'Could not contact Pterodactyl to create your account. Please try again later or ask the administrator to check the API connection.';
                return back()->withErrors(['email' => $message])->withInput($request->except('password', 'password_confirmation'));
            }
        }

        $user = DB::transaction(function () use ($data, $pteroUserId) {
            $credits = (float) Settings::get('default_credits', 100);
            $slots = max(0, (int) Settings::get('default_server_slots', 1));
            $user = User::create([
                'name' => $data['username'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'local_password_set' => true,
                'is_admin' => false,
                'credits' => $credits,
                'server_slots' => $slots,
                'ptero_user_id' => $pteroUserId,
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

        ActivityLogger::record($user->id, $user->username, 'registered', 'Created a dashboard account' . ($pteroUserId ? ' linked to Pterodactyl.' : ' (Pterodactyl sync is not configured yet).'), $request);
        Auth::login($user);
        $request->session()->regenerate();

        $message = $pteroUserId
            ? 'Account created. Your dashboard and Pterodactyl credentials are linked.'
            : 'Account created. Server deployment will be available after an administrator configures Pterodactyl.';

        return redirect()->route('dashboard')->with('success', $message);
    }

    private function oauthProviders(): array
    {
        $providers = [];
        foreach (['discord', 'google'] as $provider) {
            $providers[$provider] = Settings::enabled($provider . '_oauth_enabled')
                && trim((string) Settings::get($provider . '_oauth_client_id', '')) !== ''
                && trim((string) Settings::get($provider . '_oauth_secret', '')) !== '';
        }
        return $providers;
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            ActivityLogger::record($user->id, $user->username, 'logout', 'Signed out of TR Dashboard.', $request);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
