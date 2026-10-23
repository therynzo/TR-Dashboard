<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_oauth_id')->nullable()->unique();
            $table->string('google_oauth_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['discord_oauth_id']);
            $table->dropUnique(['google_oauth_id']);
            $table->dropColumn(['discord_oauth_id', 'google_oauth_id']);
        });
    }
};
