<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_sections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('academic_year_id');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();

            $table->uuid('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete();

            $table->uuid('subject_id');
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();

            $table->string('grade_label', 32);         // e.g. "Grade 9"
            $table->string('section_label', 64);       // e.g. "9B — Blue"

            $table->uuid('teacher_id');                // StaffMember (Slice 3)
            $table->foreign('teacher_id')->references('id')->on('staff_members')->restrictOnDelete();

            $table->string('room', 64)->nullable();
            $table->unsignedSmallInteger('capacity')->default(32);
            $table->string('status', 16)->default('draft'); // CourseStatus enum
            $table->timestamps();

            $table->unique(['tenant_id', 'academic_year_id', 'subject_id', 'section_label'], 'course_sections_year_subject_section_unique');
            $table->index(['tenant_id', 'campus_id']);
            $table->index(['tenant_id', 'teacher_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('course_enrollments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('course_section_id');
            $table->foreign('course_section_id')->references('id')->on('course_sections')->cascadeOnDelete();

            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();

            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamps();

            $table->unique(['course_section_id', 'student_id']);
            $table->index(['tenant_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_enrollments');
        Schema::dropIfExists('course_sections');
    }
};
