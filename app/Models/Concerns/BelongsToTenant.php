<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = app(TenantContext::class)->currentId();

            if ($tenantId !== null) {
                $builder->where($builder->getModel()->qualifyColumn('tenant_id'), $tenantId);
            }
        });

        static::creating(function (Model $model): void {
            if (!filled($model->getAttribute('tenant_id'))) {
                $tenantId = app(TenantContext::class)->currentId();

                if ($tenantId !== null) {
                    $model->setAttribute('tenant_id', $tenantId);
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $query, Tenant|int|null $tenant): Builder
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return $tenantId === null
            ? $query
            : $query->withoutGlobalScope('tenant')->where($query->getModel()->qualifyColumn('tenant_id'), $tenantId);
    }
}
