<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that constrains every query to the current tenant.
 *
 * If no tenant is bound in the request lifecycle (e.g. platform-admin
 * cross-tenant queries, artisan commands, queued jobs without context),
 * the scope is a no-op — callers must opt in explicitly by scoping
 * through the model's `tenant` relation or `withoutGlobalScope()`.
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId === null) {
            return;
        }
        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
