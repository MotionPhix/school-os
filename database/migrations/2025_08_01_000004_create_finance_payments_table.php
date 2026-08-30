<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('finance_invoices')->restrictOnDelete();
            $table->string('invoice_number', 32);               // snapshot
            $table->string('student_name', 160);                // snapshot

            $table->string('reference', 40);                    // "PCH-XXXXXX" / "MAN-000019"
            $table->string('method', 32);                       // PaymentMethod
            $table->string('gateway', 16);                      // paychangu | manual

            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('gateway_fee_minor')->default(0);
            $table->string('currency', 3);

            $table->string('status', 16)->default('succeeded'); // PaymentStatus
            $table->timestamp('received_at')->useCurrent();
            $table->text('note')->nullable();

            $table->uuid('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();

            // Self-referencing FK is added in a follow-up statement below:
            // inside CREATE TABLE the primary key is not yet visible to
            // Postgres for the referenced table, which raises SQLSTATE 42830.
            $table->uuid('refunded_by_payment_id')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'reference'], 'finance_payments_reference_unique');
            $table->index(['tenant_id', 'invoice_id']);
            $table->index(['tenant_id', 'gateway']);
            $table->index(['tenant_id', 'received_at']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::table('finance_payments', function (Blueprint $table): void {
            $table->foreign('refunded_by_payment_id')
                ->references('id')->on('finance_payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payments');
    }
};
