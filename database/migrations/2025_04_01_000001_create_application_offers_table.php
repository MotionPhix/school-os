<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_offers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('application_id');
            $table->foreign('application_id')->references('id')->on('applications')->cascadeOnDelete();

            $table->string('status', 16);                // OfferStatus enum
            $table->unsignedBigInteger('fee_amount');    // minor units
            $table->string('currency_code', 3);
            $table->timestamp('sent_at')->nullable();
            $table->date('expires_on')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            // One live offer per application (older offers get status=Expired/Declined and stay for audit).
            $table->index(['tenant_id', 'application_id']);
            $table->unique(['application_id', 'status'], null);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_offers');
    }
};
