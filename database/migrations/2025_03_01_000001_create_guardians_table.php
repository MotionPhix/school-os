<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('full_name');
            $table->string('avatar_initials', 4);
            $table->string('occupation')->nullable();
            $table->string('employer')->nullable();

            // Contact block (nullable — some guardians only reachable via one channel).
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_address_line')->nullable();
            $table->string('contact_city')->nullable();
            $table->string('contact_region')->nullable();

            $table->string('preferred_language', 32)->default('English');
            $table->string('portal_status')->default('invited');
            $table->timestamp('portal_last_seen_at')->nullable();
            $table->string('avatar_path')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'portal_status']);
            $table->index(['tenant_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
