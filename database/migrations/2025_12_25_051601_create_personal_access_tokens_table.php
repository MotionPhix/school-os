<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sanctum's stock migration uses `$table->morphs('tokenable')`, which makes
 * `tokenable_id` an unsigned BIGINT. Every SchoolOS model — User included —
 * uses a UUID primary key, so token issuance fails with:
 *
 *   SQLSTATE[22P02]: invalid input syntax for type bigint: "019fbbb1-..."
 *
 * Fixed at the source with `uuidMorphs` instead of patching afterwards
 * (a later-dated patch migration would be undone by this file on
 * `migrate:fresh`, since this one sorts last).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
