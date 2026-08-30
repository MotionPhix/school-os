<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base FormRequest for capability endpoints.
 *
 * - Every subclass authorizes by default; override authorize() to gate
 *   on Policies or permission keys.
 * - Exposes tenantId() so validation rules can use `exists:table,id,tenant_id,...`.
 */
abstract class CapabilityFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function tenantId(): ?string
    {
        return app(TenantContext::class)->id();
    }
}
