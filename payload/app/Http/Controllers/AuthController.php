<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\PterodactylClient;
use App\Support\ActivityLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
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
            } catch (Throwable $e) {
                report($e);
                return back()->withErrors(['email' => 'Could not create your linked Pterodactyl account. The administrator should check the Application API key and username/email availability.'])->withInput($request->except('password', 'password_confirmation'));
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
