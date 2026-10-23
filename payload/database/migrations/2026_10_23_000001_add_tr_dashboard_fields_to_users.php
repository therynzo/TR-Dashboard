<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->unique()->after('name');
            $table->boolean('is_admin')->default(false)->index();
            $table->decimal('credits', 14, 2)->default(0);
            $table->unsignedInteger('server_slots')->default(1);
            $table->unsignedBigInteger('ptero_user_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['ptero_user_id']);
            $table->dropColumn(['username', 'is_admin', 'credits', 'server_slots', 'ptero_user_id']);
        });
    }
};
