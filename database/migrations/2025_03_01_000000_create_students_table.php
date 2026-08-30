<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->uuid('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete();

            $table->string('admission_number', 64);
            $table->string('full_name');
            $table->string('preferred_name')->nullable();
            $table->string('avatar_initials', 4);
            $table->string('gender')->default('undisclosed');
            $table->date('date_of_birth');
            $table->string('stage');
            $table->string('grade_label', 64);
            $table->string('house')->nullable();
            $table->string('status')->default('prospective');
            $table->date('enrolled_on')->nullable();
            $table->string('avatar_path')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'admission_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'campus_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
