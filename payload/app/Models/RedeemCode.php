<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RedeemCode extends Model
{
    protected $fillable = ['code', 'credits', 'slot_bonus', 'max_uses', 'uses', 'expires_at', 'created_by'];

    protected function casts(): array
    {
        return ['credits' => 'decimal:2', 'expires_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redeemers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'redeem_code_redemptions')->withTimestamps();
    }
}
