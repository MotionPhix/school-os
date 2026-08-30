<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users ↔ Tenants pivot. `role_ids` is a JSON array of `roles.id`
 * UUIDs. Uses an explicit pivot model (App\Models\TenantMembership)
 * so it can carry its own UUID PK and timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->json('role_ids');
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->unique(['user_id', 'tenant_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_memberships');
    }
};
