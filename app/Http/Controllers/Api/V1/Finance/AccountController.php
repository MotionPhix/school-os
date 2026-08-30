<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domains\Finance\Services\EnsureChartOfAccounts;
use App\Enums\CurrencyCode;
use App\Http\Controllers\Api\V1\CapabilityController;
use App\Http\Resources\Api\V1\Finance\AccountResource;
use App\Http\Resources\Api\V1\Finance\LedgerPostingResource;
use App\Models\Account;
use App\Models\LedgerPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountController extends CapabilityController
{
    public function index(Request $request, EnsureChartOfAccounts $chart): JsonResponse
    {
        $this->authorize('viewAny', Account::class);

        $currency = CurrencyCode::from($request->string('currency')->toString() ?: (string) config('finance.defaults.currency'));
        // Lazily bootstrap the chart on first access — makes fresh tenants "just work".
        $chart->forCurrency($currency);

        $accounts = Account::query()
            ->where('currency', $currency->value)
            ->orderBy('type')->orderBy('kind')
            ->get();

        return response()->json([
            'data' => AccountResource::collection($accounts)->resolve(),
            'currency' => $currency->value,
        ]);
    }

    /** GET /accounts/{account}/ledger?from=&to= — raw postings for drill-down. */
    public function ledger(Request $request, Account $account): JsonResponse
    {
        $this->authorize('view', $account);

        $from = $request->string('from')->toString() ?: date('Y-01-01');
        $to = $request->string('to')->toString() ?: date('Y-12-31');

        $postings = LedgerPosting::query()
            ->where('account_id', $account->id)
            ->between($from, $to)
            ->with('entry:id,reference,memo,source_type,source_id')
            ->orderBy('occurred_on')
            ->orderBy('created_at')
            ->get();

        $debit = (int) $postings->where('side', LedgerPosting::SIDE_DEBIT)->sum('amount_minor');
        $credit = (int) $postings->where('side', LedgerPosting::SIDE_CREDIT)->sum('amount_minor');

        return response()->json([
            'data' => LedgerPostingResource::collection($postings)->resolve(),
            'meta' => [
                'account' => (new AccountResource($account))->resolve(),
                'from' => $from,
                'to' => $to,
                'debit_minor' => $debit,
                'credit_minor' => $credit,
                'balance_minor' => $debit - $credit,
            ],
        ]);
    }
}
