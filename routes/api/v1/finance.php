<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Finance\AccountController;
use App\Http\Controllers\Api\V1\Finance\FeeStructureController;
use App\Http\Controllers\Api\V1\Finance\FinanceOverviewController;
use App\Http\Controllers\Api\V1\Finance\FinancialReportController;
use App\Http\Controllers\Api\V1\Finance\InvoiceController;
use App\Http\Controllers\Api\V1\Finance\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Finance & Billing Capability Routes  (Slice 8)
|--------------------------------------------------------------------------
|
| Auto-mounted under /api/v1/finance by CapabilityRouteServiceProvider
| with ['auth:sanctum','throttle:authenticated','tenant'] middleware.
|
*/

// Fee structures
Route::get('fees', [FeeStructureController::class, 'index'])->name('fees.index');
Route::post('fees', [FeeStructureController::class, 'store'])->name('fees.store');
Route::post('fees/bulk', [FeeStructureController::class, 'bulk'])->name('fees.bulk');
Route::get('fees/{fee_structure}', [FeeStructureController::class, 'show'])->name('fees.show');
Route::patch('fees/{fee_structure}', [FeeStructureController::class, 'update'])->name('fees.update');
Route::post('fees/{fee_structure}/toggle', [FeeStructureController::class, 'toggle'])->name('fees.toggle');
Route::delete('fees/{fee_structure}', [FeeStructureController::class, 'destroy'])->name('fees.destroy');

// Invoices
Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
Route::post('invoices/bulk', [InvoiceController::class, 'bulk'])->name('invoices.bulk');
Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
Route::patch('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
Route::post('invoices/{invoice}/remind', [InvoiceController::class, 'remind'])->name('invoices.remind');
Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

// Payments
Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
Route::post('invoices/{invoice}/payments', [PaymentController::class, 'storeForInvoice'])->name('invoices.payments.store');
Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');

// Overview + reports
Route::get('overview', [FinanceOverviewController::class, 'overview'])->name('overview');
Route::get('reports/period', [FinancialReportController::class, 'period'])->name('reports.period');
Route::get('reports/monthly-trend', [FinancialReportController::class, 'monthlyTrend'])->name('reports.monthly-trend');
Route::get('reports/trial-balance', [FinancialReportController::class, 'trialBalance'])->name('reports.trial-balance');
Route::get('reports/receivables-aging', [FinancialReportController::class, 'receivablesAging'])->name('reports.receivables-aging');

// Ledger (chart of accounts + drill-down)
Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
Route::get('accounts/{account}/ledger', [AccountController::class, 'ledger'])->name('accounts.ledger');
