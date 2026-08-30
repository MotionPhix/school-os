<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_thread_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('thread_id');
            $table->foreign('thread_id')->references('id')->on('comm_message_threads')->cascadeOnDelete();

            $table->uuid('author_id')->nullable();
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
            $table->string('author_name', 160);                    // snapshot
            $table->string('author_role', 12);                     // ThreadParticipantRole

            $table->text('body');
            $table->timestamp('sent_at')->useCurrent();
            $table->boolean('read')->default(false);

            $table->timestamps();

            $table->index(['tenant_id', 'thread_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_thread_messages');
    }
};
