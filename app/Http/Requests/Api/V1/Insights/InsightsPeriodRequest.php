<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Insights;

use App\Enums\CurrencyCode;
use App\Enums\InsightPeriod;
use App\Http\Requests\Api\V1\CapabilityFormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Shared input shape for every insights endpoint. Either a named
 * `period` (preferred) or an explicit `from`/`to` custom range.
 */
final class InsightsPeriodRequest extends CapabilityFormRequest
{
    public function rules(): array
    {
        return [
            'period' => ['sometimes', new Enum(InsightPeriod::class)],
            'from' => ['sometimes', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from', 'required_with:from'],
            'format' => ['sometimes', 'in:json,csv'],
            'currency' => ['sometimes', new Enum(CurrencyCode::class)],
        ];
    }
}
