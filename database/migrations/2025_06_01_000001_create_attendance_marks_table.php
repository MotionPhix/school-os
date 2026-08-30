<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_marks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('session_id');
            $table->foreign('session_id')->references('id')->on('attendance_sessions')->cascadeOnDelete();

            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();

            $table->string('status', 16)->default('present'); // AttendanceStatus
            $table->unsignedSmallInteger('minutes_late')->nullable();
            $table->text('note')->nullable();

            $table->uuid('marked_by')->nullable();
            $table->foreign('marked_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['session_id', 'student_id'], 'attendance_marks_session_student_unique');
            $table->index(['tenant_id', 'student_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_marks');
    }
};
