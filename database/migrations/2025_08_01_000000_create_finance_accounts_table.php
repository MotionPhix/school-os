<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chart of accounts. One row per (tenant_id, kind, currency). The
 * `kind` slug is what services look accounts up by — names are for
 * humans and can be renamed without breaking anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('kind', 40);                         // AccountKind enum value
            $table->string('type', 16);                         // AccountType enum value
            $table->string('name', 120);
            $table->string('currency', 3);
            $table->boolean('is_system')->default(true);        // seeded vs. user-created
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['tenant_id', 'kind', 'currency'], 'finance_accounts_kind_unique');
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_accounts');
    }
};
