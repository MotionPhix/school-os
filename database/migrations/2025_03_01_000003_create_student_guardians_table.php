<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_guardians', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->uuid('guardian_id');
            $table->foreign('guardian_id')->references('id')->on('guardians')->cascadeOnDelete();

            $table->string('relationship', 64);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['student_id', 'guardian_id']);
            $table->index(['tenant_id', 'guardian_id']);
            $table->index(['tenant_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
    }
};
