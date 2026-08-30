<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_stage_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('application_id');
            $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();

            $table->string('from_stage', 32)->nullable();
            $table->string('to_stage', 32);
            $table->text('note')->nullable();
            $table->string('actor_name');
            $table->uuid('actor_id')->nullable();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'application_id', 'occurred_at'], 'stage_events_tenant_app_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_stage_events');
    }
};
