<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('label', 32); // "2026 / 2027"
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('planning');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'label']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
