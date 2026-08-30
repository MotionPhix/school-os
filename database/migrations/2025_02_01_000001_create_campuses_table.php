<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campuses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('name');
            $table->string('code', 32);
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('operational');
            $table->string('address_line');
            $table->string('city');
            $table->string('region');
            $table->string('timezone');
            $table->unsignedInteger('building_count')->default(0);
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campuses');
    }
};
