<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('academic_year_id');
            $table->uuid('campus_id')->nullable(); // null = all campuses
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
            $table->foreign('campus_id')->references('id')->on('campuses')->nullOnDelete();

            $table->string('title');
            $table->string('kind');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('all_day')->default(true);
            $table->string('audience')->default('whole_school');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'academic_year_id', 'starts_on']);
            $table->index(['tenant_id', 'campus_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
