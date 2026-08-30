<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('academic_year_id');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();

            $table->uuid('term_id');
            $table->foreign('term_id')->references('id')->on('terms')->restrictOnDelete();

            $table->string('name', 120);           // "Term 3 Mid-Term"
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 16)->default('draft'); // ExamPeriodStatus

            $table->timestamps();

            $table->index(['tenant_id', 'term_id']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'term_id', 'name'], 'exam_periods_tenant_term_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_periods');
    }
};
