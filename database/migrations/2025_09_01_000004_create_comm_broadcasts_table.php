<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Broadcasts — bulk SMS/email campaigns. `cost_minor` is a denormalised
 * snapshot in tambala so the overview KPIs can sum spend without
 * touching a settlement/webhook table. `template_snippet` is the raw
 * body with placeholders like `{balance}` for the SMS/email renderer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_broadcasts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('name', 200);
            $table->string('channel', 12);                         // CommunicationChannel
            $table->string('audience', 20);                        // CommunicationAudience
            $table->string('audience_label', 160);
            $table->text('template_snippet');

            $table->string('status', 12)->default('draft');        // BroadcastStatus

            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedBigInteger('cost_minor')->default(0);
            $table->string('currency', 3);

            $table->uuid('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'channel']);
            $table->index(['tenant_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_broadcasts');
    }
};
