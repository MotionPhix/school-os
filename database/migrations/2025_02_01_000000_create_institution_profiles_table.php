<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('name');
            $table->string('short_name', 32);
            $table->string('motto')->nullable();
            $table->unsignedSmallInteger('established_year');
            $table->string('type')->default('secondary');
            $table->string('accreditation_status')->default('pending');
            $table->string('accreditation_body')->nullable();
            $table->unsignedInteger('student_capacity')->default(0);
            $table->json('languages_of_instruction');

            // Contact block (denormalised — one profile per tenant).
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->string('contact_website')->nullable();
            $table->string('address_line');
            $table->string('city');
            $table->string('region');
            $table->string('postal_code')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_profiles');
    }
};
