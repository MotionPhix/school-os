<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comm_broadcasts', function (Blueprint $table): void {
            $table->timestamp('delivery_alerted_at')->nullable()->after('failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('comm_broadcasts', function (Blueprint $table): void {
            $table->dropColumn('delivery_alerted_at');
        });
    }
};
