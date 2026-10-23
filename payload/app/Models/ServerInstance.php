<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerInstance extends Model
{
    protected $fillable = [
        'user_id', 'name', 'ptero_server_id', 'ptero_identifier', 'status', 'software',
        'credits_paid', 'expires_at', 'suspended_at', 'delete_at', 'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'credits_paid' => 'decimal:2',
            'expires_at' => 'datetime',
            'suspended_at' => 'datetime',
            'delete_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRenewable(): bool
    {
        return in_array($this->status, ['active', 'suspended'], true) && !$this->deleted_at;
    }
}
