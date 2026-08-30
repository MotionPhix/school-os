<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('reference', 32);

            // Applicant identity (embedded — prospective students are not
            // Students until enrolled). On enrollment we mint a Student
            // and stamp its id back onto `student_id` for cross-domain joins.
            $table->string('applicant_full_name');
            $table->string('applicant_preferred_name')->nullable();
            $table->string('avatar_initials', 4);
            $table->date('date_of_birth');
            $table->string('gender', 16);

            // Guardian contact (denormalized snapshot until Slice 3 Guardian is linked).
            $table->string('guardian_name');
            $table->string('guardian_email')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->uuid('guardian_id')->nullable(); // FK to guardians (Slice 3), optional
            $table->foreign('guardian_id')->references('id')->on('guardians')->nullOnDelete();

            $table->uuid('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete();
            $table->uuid('academic_year_id');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();

            $table->string('intended_stage', 32);       // StudentStage enum value
            $table->string('intended_grade_label', 32);
            $table->string('source', 32);               // ApplicationSource enum value
            $table->string('stage', 32);                // PipelineStage enum value

            $table->unsignedTinyInteger('assessment_score')->nullable();
            $table->unsignedTinyInteger('interview_score')->nullable();

            $table->uuid('student_id')->nullable();
            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'stage']);
            $table->index(['tenant_id', 'academic_year_id']);
            $table->index(['tenant_id', 'campus_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
