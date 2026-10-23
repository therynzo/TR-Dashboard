<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogger
{
    public static function record(?int $userId, string $actor, string $action, string $description, ?Request $request = null): void
    {
        try {
            ActivityLog::create([
                'user_id' => $userId,
                'actor_name' => $actor,
                'action' => $action,
                'description' => $description,
                'ip_address' => $request?->ip(),
            ]);
        } catch (\Throwable) {
            // Activity logging must never interrupt a hosting operation.
        }
    }
}
