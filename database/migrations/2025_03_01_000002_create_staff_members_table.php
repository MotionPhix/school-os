<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->uuid('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->restrictOnDelete();
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('staff_number', 32);
            $table->string('full_name');
            $table->string('avatar_initials', 4);
            $table->string('title');
            $table->string('category')->default('teaching');
            $table->string('department');
            $table->string('employment_type')->default('permanent');
            $table->string('status')->default('active');

            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_address_line')->nullable();
            $table->string('contact_city')->nullable();
            $table->string('contact_region')->nullable();

            $table->json('subjects_taught');
            $table->date('hired_on');
            $table->string('avatar_path')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'staff_number']);
            $table->index(['tenant_id', 'campus_id', 'category']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_members');
    }
};
