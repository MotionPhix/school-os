<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dunning support: records the last time a guardian was chased for the
 * outstanding balance so bursars can see (and bulk-send) reminders
 * without spamming the same household twice a day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_invoices', function (Blueprint $table): void {
            $table->timestamp('last_reminded_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('finance_invoices', function (Blueprint $table): void {
            $table->dropColumn('last_reminded_at');
        });
    }
};
