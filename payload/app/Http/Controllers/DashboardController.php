<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $servers = $user->servers()->latest()->get();
        $activeCount = $user->servers()->whereIn('status', ['active', 'suspended'])->count();
        $logs = ActivityLog::query()->where('user_id', $user->id)->latest()->limit(6)->get();

        return view('dashboard.index', [
            'servers' => $servers->take(5),
            'activeCount' => $activeCount,
            'remainingSlots' => max(0, (int) $user->server_slots - $activeCount),
            'logs' => $logs,
        ]);
    }
}
