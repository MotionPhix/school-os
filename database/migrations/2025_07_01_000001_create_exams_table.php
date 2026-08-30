<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('period_id');
            $table->foreign('period_id')->references('id')->on('exam_periods')->cascadeOnDelete();

            $table->uuid('course_section_id');
            $table->foreign('course_section_id')->references('id')->on('course_sections')->restrictOnDelete();

            $table->string('paper_title', 160);        // "Paper 1 — Algebra"
            $table->date('scheduled_on');
            $table->string('starts_at', 5);            // "HH:mm"
            $table->unsignedSmallInteger('duration_minutes')->default(90);
            $table->string('room', 64)->nullable();

            $table->unsignedTinyInteger('max_score')->default(100);
            $table->unsignedTinyInteger('pass_mark')->default(40);

            $table->string('status', 16)->default('draft'); // ExamStatus

            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'period_id']);
            $table->index(['tenant_id', 'course_section_id', 'scheduled_on']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
