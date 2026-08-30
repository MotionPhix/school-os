<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // Polymorphic subject — string discriminator matches URL segments
            // and PersonSubject enum values (students|guardians|staff).
            $table->string('subject_type', 16);
            $table->uuid('subject_id');

            $table->string('name');
            $table->string('mime', 128);
            $table->unsignedInteger('size');
            $table->string('storage_path');
            $table->uuid('uploaded_by')->nullable();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->index(['tenant_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_documents');
    }
};
