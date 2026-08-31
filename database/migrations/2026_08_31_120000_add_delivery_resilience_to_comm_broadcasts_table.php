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
            $table->unsignedInteger('delivery_retry_count')->default(0)->after('failed_count');
            $table->timestamp('delivery_next_retry_at')->nullable()->after('delivery_retry_count');
            $table->timestamp('delivery_dead_lettered_at')->nullable()->after('delivery_next_retry_at');
            $table->json('failure_reasons')->nullable()->after('delivery_dead_lettered_at');
        });
    }

    public function down(): void
    {
        Schema::table('comm_broadcasts', function (Blueprint $table): void {
            $table->dropColumn([
                'delivery_retry_count',
                'delivery_next_retry_at',
                'delivery_dead_lettered_at',
                'failure_reasons',
            ]);
        });
    }
};
