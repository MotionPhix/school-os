<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('exam_id');
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();

            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();

            $table->unsignedSmallInteger('score')->nullable(); // 0..max_score, null = ungraded
            $table->string('band', 2)->nullable();             // GradeBand or null
            $table->text('remarks')->nullable();

            $table->uuid('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id'], 'exam_results_exam_student_unique');
            $table->index(['tenant_id', 'student_id']);
            $table->index(['tenant_id', 'exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};
