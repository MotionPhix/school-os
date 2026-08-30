<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Converts starter-kit `users.id` to UUID and adds Identity fields.
 * Column types stay `string` where an enum backs them, keeping the
 * enum class as the source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('status')->default(UserStatus::Active->value);
            $table->timestamp('last_active_at')->nullable();
            $table->boolean('mfa_enabled')->default(false);
            $table->foreignUuid('active_tenant_id')->nullable()
                ->constrained('tenants')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
