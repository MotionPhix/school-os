<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base API resource. Enforces:
 * - snake_case keys (default JsonResource behavior when we spell them out).
 * - ISO-8601 timestamps via toIso8601String() in subclasses.
 * - No leakage of `tenant_id` unless the subclass opts in — tenant
 *   scoping is already enforced server-side and the field is noise
 *   in the presentation layer.
 */
abstract class CapabilityResource extends JsonResource
{
    /**
     * Helper for consistent nullable ISO timestamp serialization.
     */
    protected function iso(?DateTimeInterface $dt): ?string
    {
        return $dt?->format(DateTimeInterface::ATOM);
    }
}
