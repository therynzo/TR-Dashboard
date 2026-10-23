<?php

namespace App\Http\Controllers;

use App\Services\PterodactylClient;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.show', ['account' => $request->user()]);
    }

    public function update(Request $request, PterodactylClient $pterodactyl): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        if ($user->ptero_user_id && ($data['username'] !== $user->username || $data['email'] !== $user->email)) {
            try {
                $pterodactyl->updateUser((int) $user->ptero_user_id, [
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'first_name' => $data['username'],
                    'last_name' => 'User',
                    'root_admin' => false,
                ]);
            } catch (Throwable $e) {
                report($e);
                return back()->with('error', 'Your linked Pterodactyl profile could not be updated. No local profile changes were saved.');
            }
        }

        $user->username = $data['username'];
        $user->name = $data['username'];
        $user->email = $data['email'];
        $user->save();

        ActivityLogger::record($user->id, $user->username, 'profile_updated', 'Updated account profile details.', $request);
        return back()->with('success', 'Your account email and username have been updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        $rules = ['password' => ['required', 'string', 'min:8', 'confirmed']];
        if ($user->local_password_set) {
            $rules['current_password'] = ['required', 'current_password'];
        }
        $data = $request->validate($rules);

        $user->password = Hash::make($data['password']);
        $user->local_password_set = true;
        $user->save();
        Auth::logoutOtherDevices($data['password']);

        ActivityLogger::record($request->user()->id, $request->user()->username, 'password_changed', 'Changed account password.', $request);
        return back()->with('success', 'Your password has been changed.');
    }
}
