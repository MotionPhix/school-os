<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Finance;

use App\Enums\CurrencyCode;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Shared shape for period reports (P&L, monthly trend, aging).
 */
final class PeriodReportRequest extends CapabilityFormRequest
{
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'as_of' => ['sometimes', 'date_format:Y-m-d'],
            'currency' => ['sometimes', new Enum(CurrencyCode::class)],
        ];
    }
}
