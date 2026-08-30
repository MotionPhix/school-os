<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            // Dot-notation Business Event name, e.g. "identity.user.suspended".
            $table->string('name');
            $table->foreignUuid('actor_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('actor_name')->default('System');
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->string('summary');
            $table->json('metadata');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'name']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
