<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal entries — the header record for a balanced set of debits
 * and credits. `source_type` + `source_id` link the entry back to the
 * business event that caused it (invoice, payment, refund) so reports
 * can drill from a P&L line down to the underlying documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_journal_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->date('occurred_on');
            $table->string('reference', 64);                    // "INV-... issued", "PAY-...", "INV-... void"
            $table->string('memo', 240)->nullable();

            $table->string('source_type', 40)->nullable();      // "invoice" | "payment" | "adjustment"
            $table->uuid('source_id')->nullable();
            $table->string('currency', 3);
            $table->boolean('is_reversal')->default(false);

            // Self-referencing FK is added below in a separate Schema::table
            // because Postgres cannot see the table's own primary key while
            // the CREATE TABLE statement is still executing (SQLSTATE 42830).
            $table->uuid('reverses_entry_id')->nullable();

            $table->uuid('posted_by')->nullable();
            $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('posted_at')->useCurrent();

            $table->timestamps();

            $table->index(['tenant_id', 'occurred_on']);
            $table->index(['tenant_id', 'source_type', 'source_id']);
        });

        Schema::table('finance_journal_entries', function (Blueprint $table): void {
            $table->foreign('reverses_entry_id')
                ->references('id')->on('finance_journal_entries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_journal_entries');
    }
};
