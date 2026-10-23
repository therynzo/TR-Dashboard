<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->longText('setting_value')->nullable();
            $table->timestamps();
        });

        Schema::create('server_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('ptero_server_id')->nullable()->index();
            $table->string('ptero_identifier')->nullable()->index();
            $table->string('status', 24)->default('active')->index();
            $table->string('software', 60)->default('Default');
            $table->decimal('credits_paid', 14, 2)->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('delete_at')->nullable()->index();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('type', 32);
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('redeem_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->decimal('credits', 14, 2)->default(0);
            $table->unsignedInteger('slot_bonus')->default(0);
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('redeem_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('redeem_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['redeem_code_id', 'user_id']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->default('System');
            $table->string('action', 60);
            $table->text('description');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('redeem_code_redemptions');
        Schema::dropIfExists('redeem_codes');
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('server_instances');
        Schema::dropIfExists('settings');
    }
};
