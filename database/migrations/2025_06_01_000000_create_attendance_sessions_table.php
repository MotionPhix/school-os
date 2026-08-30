<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('course_section_id');
            $table->foreign('course_section_id')->references('id')->on('course_sections')->cascadeOnDelete();

            $table->date('date');
            $table->unsignedTinyInteger('period'); // 1..N per timetable grid

            $table->string('status', 16)->default('draft'); // AttendanceSessionStatus

            $table->unsignedSmallInteger('present_count')->default(0);
            $table->unsignedSmallInteger('absent_count')->default(0);
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->unsignedSmallInteger('excused_count')->default(0);
            $table->unsignedSmallInteger('total_count')->default(0);

            $table->uuid('opened_by')->nullable();
            $table->foreign('opened_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('taken_at')->nullable(); // set on submit
            $table->timestamps();

            $table->unique(['course_section_id', 'date', 'period'], 'attendance_sessions_section_date_period_unique');
            $table->index(['tenant_id', 'date']);
            $table->index(['tenant_id', 'course_section_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
