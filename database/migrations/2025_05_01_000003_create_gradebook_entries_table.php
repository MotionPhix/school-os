<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gradebook_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('course_section_id');
            $table->foreign('course_section_id')->references('id')->on('course_sections')->cascadeOnDelete();

            $table->uuid('term_id');
            $table->foreign('term_id')->references('id')->on('terms')->restrictOnDelete();

            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();

            $table->unsignedTinyInteger('continuous_assessment')->default(0); // 0..CA max
            $table->unsignedTinyInteger('exam_score')->default(0);            // 0..exam max
            $table->unsignedTinyInteger('total')->default(0);                 // 0..100
            $table->string('band', 2)->default('F');                          // GradeBand
            $table->text('remarks')->nullable();

            $table->uuid('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_section_id', 'term_id', 'student_id'], 'gradebook_section_term_student_unique');
            $table->index(['tenant_id', 'term_id']);
            $table->index(['tenant_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gradebook_entries');
    }
};
