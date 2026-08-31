<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform billing — the platform charges TENANTS (not individual users)
 * for the subscription. Invoices are issued per tenant per period;
 * payments are collected via PayChangu standard checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('period', 7); // YYYY-MM billing period
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->string('currency', 3)->default('MWK');
            $table->string('status', 16)->default('pending'); // pending | paid | overdue
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'period']);
            $table->index('status');
        });

        Schema::create('platform_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('platform_invoice_id');
            $table->string('tx_ref', 64)->unique();
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->string('currency', 3)->default('MWK');
            $table->string('status', 16)->default('pending'); // pending | succeeded | failed
            $table->string('checkout_url')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->foreign('platform_invoice_id')->references('id')->on('platform_invoices')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payments');
        Schema::dropIfExists('platform_invoices');
    }
};
