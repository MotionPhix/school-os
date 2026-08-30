<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Message threads — two-way, small-group DM conversations, typically
 * between a staff member and a guardian about a specific student.
 * `student_id` is nullable — some threads (e.g. bursar↔guardian fee
 * chats) are not scoped to a single student.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_message_threads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('subject', 200);
            $table->string('status', 12)->default('open');         // MessageThreadStatus

            $table->uuid('student_id')->nullable();
            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
            $table->string('student_name', 160)->nullable();       // snapshot

            $table->string('last_message_preview', 240)->default('');
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'last_message_at']);
            $table->index(['tenant_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_message_threads');
    }
};
