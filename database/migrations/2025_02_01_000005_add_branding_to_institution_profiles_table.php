<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Branding + accreditation detail for the institution profile.
 *
 * `logo_url` holds a storage-relative public URL (see PUT /institution/profile/logo);
 * the frontend accepts either a CDN URL or a data URL, so the column is a plain
 * string rather than a media relation — the crest is a singleton per tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_profiles', function (Blueprint $table): void {
            $table->string('logo_url', 2048)->nullable()->after('motto');
            $table->string('accreditation_number', 120)->nullable()->after('accreditation_body');
            $table->date('accredited_until')->nullable()->after('accreditation_number');
        });
    }

    public function down(): void
    {
        Schema::table('institution_profiles', function (Blueprint $table): void {
            $table->dropColumn(['logo_url', 'accreditation_number', 'accredited_until']);
        });
    }
};
