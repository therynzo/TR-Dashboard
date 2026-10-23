<?php

namespace App\Console\Commands;

use App\Models\CreditTransaction;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BootstrapTrDashboard extends Command
{
    protected $signature = 'tr:bootstrap';
    protected $description = 'Initialize TR Dashboard settings and create its first administrator.';

    public function handle(): int
    {
        foreach (Settings::defaults() as $key => $value) {
            if (!Settings::has($key)) {
                Settings::set($key, $value);
            }
        }

        $email = trim((string) (getenv('TR_BOOTSTRAP_ADMIN_EMAIL') ?: ''));
        $username = trim((string) (getenv('TR_BOOTSTRAP_ADMIN_USERNAME') ?: ''));
        $password = (string) (getenv('TR_BOOTSTRAP_ADMIN_PASSWORD') ?: '');

        if ($email !== '' && $username !== '' && $password !== '' && !User::where('is_admin', true)->exists()) {
            $admin = User::create([
                'name' => $username,
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($password),
                'is_admin' => true,
                'credits' => 0,
                'server_slots' => 0,
            ]);
            $this->info('Created administrator account: ' . $admin->email);
        } elseif (User::where('is_admin', true)->exists()) {
            $this->line('An administrator already exists; no additional administrator was created.');
        } elseif ($email !== '' || $username !== '' || $password !== '') {
            $this->error('Admin bootstrap data is incomplete. No administrator was created.');
            return self::FAILURE;
        } else {
            $this->line('Settings initialized. Admin bootstrap values were not supplied.');
        }

        $this->info('TR Dashboard initialized.');
        return self::SUCCESS;
    }
}
