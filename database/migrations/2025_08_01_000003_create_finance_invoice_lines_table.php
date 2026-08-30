<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('finance_invoices')->cascadeOnDelete();

            $table->uuid('fee_structure_id')->nullable();
            $table->foreign('fee_structure_id')->references('id')->on('finance_fee_structures')->nullOnDelete();

            $table->string('description', 240);
            $table->string('category', 20);                     // FeeCategory
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('amount_minor');         // quantity * unit
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_invoice_lines');
    }
};
