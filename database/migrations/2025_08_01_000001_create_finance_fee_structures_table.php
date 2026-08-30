<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_fee_structures', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('academic_year_id')->nullable();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->string('academic_year_label', 40);          // denormalised for reporting
            $table->string('grade_label', 40);                  // "All grades" or a specific band

            $table->string('name', 120);
            $table->string('category', 20);                     // FeeCategory
            $table->string('cycle', 20);                        // BillingCycle
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('applies_to_student_count')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_fee_structures');
    }
};
