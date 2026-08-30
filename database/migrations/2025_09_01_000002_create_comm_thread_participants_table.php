<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thread participants — who is in a thread and in what role. `user_id`
 * is nullable because guardians and students may not always have a
 * portal login yet; `name` + `role` are always snapshotted so the
 * conversation stays readable even after user records evolve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_thread_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('thread_id');
            $table->foreign('thread_id')->references('id')->on('comm_message_threads')->cascadeOnDelete();

            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('name', 160);
            $table->string('role', 12);                            // ThreadParticipantRole
            $table->string('avatar_initials', 8);

            $table->timestamps();

            $table->index(['tenant_id', 'thread_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_thread_participants');
    }
};
