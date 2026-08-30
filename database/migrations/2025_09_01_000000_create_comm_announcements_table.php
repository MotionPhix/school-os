<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements — outbound one-way messages fanned out to an audience
 * over one or more channels (SMS / Email / In-app). Delivery counters
 * are denormalised on the row so the overview KPIs are a single query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_announcements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('title', 200);
            $table->text('body');

            $table->string('audience', 20);                        // CommunicationAudience
            $table->string('audience_label', 160);                 // resolved label snapshot
            $table->json('channels');                              // list<CommunicationChannel>

            $table->string('status', 16)->default('draft');        // AnnouncementStatus

            $table->uuid('author_id')->nullable();
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
            $table->string('author_name', 160);                    // snapshot

            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'scheduled_for']);
            $table->index(['tenant_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_announcements');
    }
};
