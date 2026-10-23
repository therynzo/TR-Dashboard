<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTrAdmin extends Command
{
    protected $signature = 'tr:admin-create';
    protected $description = 'Interactively create an additional TR Dashboard administrator.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->ask('Administrator email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address.');
            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error('A user with that email already exists. Use the existing account or choose a different email address.');
            return self::FAILURE;
        }

        $username = trim((string) $this->ask('Administrator username'));
        if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username) || User::where('username', $username)->exists()) {
            $this->error('Username must be unique and contain 3–32 letters, numbers, or underscores.');
            return self::FAILURE;
        }

        $password = (string) $this->secret('Administrator password (at least 12 characters)');
        if (strlen($password) < 12) {
            $this->error('Use a password with at least 12 characters.');
            return self::FAILURE;
        }
        $confirmation = (string) $this->secret('Confirm password');
        if (!hash_equals($password, $confirmation)) {
            $this->error('Passwords do not match. No account was created.');
            return self::FAILURE;
        }

        User::create([
            'name' => $username,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'local_password_set' => true,
            'is_admin' => true,
            'credits' => 0,
            'server_slots' => 0,
        ]);

        $this->info('Administrator account created successfully.');
        $this->line('Email: ' . $email);
        $this->line('Sign in through your dashboard login page.');
        return self::SUCCESS;
    }
}
