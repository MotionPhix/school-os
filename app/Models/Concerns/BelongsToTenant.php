<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant-scoped.
 *
 * - Registers TenantScope so every query is filtered by the current tenant.
 * - Auto-fills `tenant_id` on create from TenantContext.
 * - Exposes `->tenant()` relation.
 *
 * Tables using this trait MUST have a `tenant_id` UUID column with a
 * foreign key to `tenants.id`.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            if (empty($model->tenant_id)) {
                $tenantId = app(TenantContext::class)->id();
                if ($tenantId !== null) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
