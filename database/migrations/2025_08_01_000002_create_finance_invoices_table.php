<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices. `number` is human-readable and unique per tenant. Money
 * fields are integer minor units so we never touch floats. Student
 * and guardian labels are snapshotted at issue time — an invoice
 * dated 2024 should not silently rename because someone edited the
 * student record in 2026.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('number', 32);

            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
            $table->string('student_name', 160);
            $table->string('student_initials', 8);
            $table->string('grade_label', 40);
            $table->string('guardian_name', 160)->default('');

            $table->uuid('academic_year_id')->nullable();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->string('academic_year_label', 40);
            $table->uuid('term_id')->nullable();
            $table->foreign('term_id')->references('id')->on('terms')->nullOnDelete();
            $table->string('term_label', 40);

            $table->date('issued_on');
            $table->date('due_on');

            $table->string('currency', 3);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->unsignedBigInteger('balance_minor')->default(0);

            $table->string('status', 20)->default('draft');     // InvoiceStatus

            $table->timestamps();

            $table->unique(['tenant_id', 'number'], 'finance_invoices_number_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'student_id']);
            $table->index(['tenant_id', 'issued_on']);
            $table->index(['tenant_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_invoices');
    }
};
