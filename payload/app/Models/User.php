<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'is_admin', 'credits', 'server_slots', 'ptero_user_id', 'discord_oauth_id', 'google_oauth_id', 'local_password_set',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'local_password_set' => 'boolean',
            'credits' => 'decimal:2',
        ];
    }

    public function servers()
    {
        return $this->hasMany(ServerInstance::class);
    }

    public function creditTransactions()
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }
}
