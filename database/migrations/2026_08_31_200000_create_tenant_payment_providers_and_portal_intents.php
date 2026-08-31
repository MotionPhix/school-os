<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-owned payment receiving:
 *  1. tenant_payment_providers — the TENANT's own PayChangu credentials
 *     (money lands in their bank account when parents pay).
 *  2. portal_payment_intents — a checkout attempt made by a guardian in the
 *     parents portal; on successful verification it is booked through the
 *     tenant's finance domain (RecordPayment).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_payment_providers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->string('provider', 32)->default('paychangu');
            $table->text('secret_key')->nullable(); // encrypted
            $table->text('public_key')->nullable(); // encrypted
            $table->string('mode', 8)->default('test'); // test | live
            $table->boolean('is_active')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('portal_payment_intents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('invoice_id');
            $table->uuid('guardian_user_id');
            $table->string('tx_ref', 64)->unique();
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->string('currency', 3)->default('MWK');
            $table->string('status', 16)->default('pending'); // pending | succeeded | failed
            $table->string('checkout_url')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_payment_intents');
        Schema::dropIfExists('tenant_payment_providers');
    }
};
