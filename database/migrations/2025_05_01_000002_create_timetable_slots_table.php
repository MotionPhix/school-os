<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_slots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('course_section_id');
            $table->foreign('course_section_id')->references('id')->on('course_sections')->cascadeOnDelete();

            $table->string('weekday', 4);              // Weekday enum value
            $table->unsignedTinyInteger('period');
            $table->string('starts_at', 5);            // "HH:mm"
            $table->string('ends_at', 5);
            $table->string('room', 64)->nullable();    // override on section.room
            $table->timestamps();

            // A course section owns at most one slot per weekday+period.
            $table->unique(['course_section_id', 'weekday', 'period'], 'timetable_section_day_period_unique');
            $table->index(['tenant_id', 'weekday', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
