<?php

namespace App\Console\Commands;

use App\Models\ServerInstance;
use App\Services\PterodactylClient;
use App\Support\ActivityLogger;
use App\Support\Settings;
use Illuminate\Console\Command;
use Throwable;

class ManageServerLifecycle extends Command
{
    protected $signature = 'tr:server-lifecycle';
    protected $description = 'Suspend expired servers and permanently delete them after their renewal grace period.';

    public function handle(PterodactylClient $client): int
    {
        $suspended = 0;
        $deleted = 0;

        ServerInstance::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(50, function ($servers) use ($client, &$suspended) {
                foreach ($servers as $server) {
                    try {
                        $client->suspend((int) $server->ptero_server_id);
                        $server->update([
                            'status' => 'suspended',
                            'suspended_at' => now(),
                            'delete_at' => now()->addDays(max(1, (int) Settings::get('server_grace_days', 7))),
                        ]);
                        ActivityLogger::record($server->user_id, $server->user?->username ?? 'System', 'server_auto_suspended', 'Server ' . $server->name . ' expired and was automatically suspended.');
                        $suspended++;
                        $this->line('Suspended expired server #' . $server->id . ' (' . $server->name . ').');
                    } catch (Throwable $e) {
                        report($e);
                        $this->error('Could not suspend server #' . $server->id . '. It will be retried on the next scheduler run.');
                    }
                }
            });

        ServerInstance::query()
            ->where('status', 'suspended')
            ->whereNotNull('delete_at')
            ->where('delete_at', '<=', now())
            ->orderBy('id')
            ->chunkById(50, function ($servers) use ($client, &$deleted) {
                foreach ($servers as $server) {
                    try {
                        $client->deleteServer((int) $server->ptero_server_id, true);
                        $server->update(['status' => 'deleted', 'deleted_at' => now(), 'delete_at' => null]);
                        ActivityLogger::record($server->user_id, $server->user?->username ?? 'System', 'server_auto_deleted', 'Server ' . $server->name . ' passed the renewal grace period and was permanently deleted.');
                        $deleted++;
                        $this->line('Deleted expired server #' . $server->id . ' (' . $server->name . ').');
                    } catch (Throwable $e) {
                        report($e);
                        $this->error('Could not delete server #' . $server->id . '. It will be retried on the next scheduler run.');
                    }
                }
            });

        $this->info("Lifecycle pass complete. Suspended: {$suspended}; deleted: {$deleted}.");
        return self::SUCCESS;
    }
}
