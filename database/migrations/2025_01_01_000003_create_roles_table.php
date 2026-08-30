<?php

declare(strict_types=1);

use App\Enums\RoleScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Nullable = platform-level role, shared across all tenants.
            $table->foreignUuid('tenant_id')->nullable()
                ->constrained('tenants')->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->text('description');
            $table->string('scope')->default(RoleScope::Tenant->value);
            $table->boolean('is_system')->default(false);
            $table->json('permission_keys');
            $table->timestamps();

            // A role key is unique within its tenant (and once globally
            // for platform roles, where tenant_id IS NULL).
            $table->unique(['tenant_id', 'key']);
            $table->index(['tenant_id', 'is_system']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
