<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger postings — the actual debit/credit lines. Every entry must
 * balance (SUM(debit) == SUM(credit)); enforced in the writer service.
 * We store side + amount separately (rather than signed amount) so
 * reports can sum debits and credits independently for trial balances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_ledger_postings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('journal_entry_id');
            $table->foreign('journal_entry_id')->references('id')->on('finance_journal_entries')->cascadeOnDelete();

            $table->uuid('account_id');
            $table->foreign('account_id')->references('id')->on('finance_accounts')->restrictOnDelete();

            $table->string('side', 6);                          // "debit" | "credit"
            $table->unsignedBigInteger('amount_minor');
            $table->date('occurred_on');                        // denormalised from entry for fast range queries
            $table->string('currency', 3);
            $table->string('memo', 240)->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'account_id', 'occurred_on']);
            $table->index(['tenant_id', 'occurred_on']);
            $table->index(['tenant_id', 'journal_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_ledger_postings');
    }
};
