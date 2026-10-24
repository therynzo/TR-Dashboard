<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

class Settings
{
    private const SECRET_KEYS = [
        'ptero_api_key',
        'discord_oauth_secret',
        'google_oauth_secret',
        'linkvertise_anti_bypass_token',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Setting::query()->where('setting_key', $key)->value('setting_value');
        if ($value === null) {
            return $default;
        }

        if (in_array($key, self::SECRET_KEYS, true) && $value !== '') {
            // Older package versions stored Pterodactyl secrets as plaintext. Keep
            // those values readable so an update does not silently break the API;
            // secrets written by this version are Laravel encrypted payloads.
            if (!str_starts_with($value, 'eyJpdiI6')) {
                return $value;
            }
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable) {
                // A value that looks encrypted but cannot be decrypted must fail closed.
                return '';
            }
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $value = is_scalar($value) || $value === null ? (string) ($value ?? '') : json_encode($value);
        if (in_array($key, self::SECRET_KEYS, true) && $value !== '') {
            $value = Crypt::encryptString($value);
        }

        Setting::query()->updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value]
        );
    }

    public static function has(string $key): bool
    {
        return Setting::query()->where('setting_key', $key)->exists();
    }

    public static function enabled(string $key): bool
    {
        return filter_var(self::get($key, false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function publicValues(): array
    {
        $keys = ['brand_name', 'theme_color', 'logo_url', 'announcement', 'ptero_url', 'discord_url'];
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = self::get($key, self::defaults()[$key] ?? '');
        }
        return $values;
    }

    public static function defaults(): array
    {
        return [
            // Brand and general links
            'brand_name' => 'TR Dashboard',
            'theme_color' => '#7c3aed',
            'logo_url' => '/tr-logo.png',
            'announcement' => 'Welcome to TR Dashboard. Your server hosting, one clean panel.',
            'discord_url' => '',
            // Pterodactyl Application API and deployment profile
            'ptero_url' => '',
            'ptero_api_key' => '',
            'ptero_location_id' => '',
            'ptero_node_id' => '',
            'ptero_nest_id' => '',
            'ptero_egg_id' => '',
            'ptero_docker_image' => 'ghcr.io/pterodactyl/yolks:java_21',
            'ptero_startup' => 'java -Xms128M -XX:MaxRAMPercentage=95.0 -jar {{SERVER_JARFILE}}',
            'ptero_environment' => '{"SERVER_JARFILE":"server.jar","MINECRAFT_VERSION":"latest","BUILD_NUMBER":"latest"}',
            'ptero_memory' => '2048',
            'ptero_disk' => '5120',
            'ptero_cpu' => '100',
            'ptero_swap' => '0',
            'ptero_io' => '500',
            'ptero_databases' => '1',
            'ptero_allocations' => '1',
            'ptero_backups' => '1',
            // Account and server defaults
            'default_credits' => '100',
            'default_server_slots' => '1',
            'server_create_cost' => '0',
            'server_renew_cost' => '0',
            'server_lifetime_hours' => '48',
            'server_grace_days' => '7',
            // OAuth is intentionally disabled until an administrator turns it on.
            'discord_oauth_enabled' => '0',
            'discord_oauth_client_id' => '',
            'discord_oauth_secret' => '',
            'google_oauth_enabled' => '0',
            'google_oauth_client_id' => '',
            'google_oauth_secret' => '',
            // Linkvertise rewards are intentionally disabled until fully configured.
            'linkvertise_enabled' => '0',
            'linkvertise_publisher_id' => '',
            'linkvertise_url' => '',
            'linkvertise_anti_bypass_token' => '',
            'linkvertise_reward_credits' => '10',
            'linkvertise_daily_limit' => '1',
        ];
    }
}
