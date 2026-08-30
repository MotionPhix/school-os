<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('legal_name');
            $table->string('country_code', 2);
            $table->string('timezone');
            $table->string('currency_code', 3);
            $table->string('tier')->default('institution'); // foundation|institution|group
            $table->string('status')->default('active');    // active|suspended|archived
            $table->timestamps();

            $table->index(['status', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
