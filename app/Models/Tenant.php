<?php

namespace App\Models;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'legal_name',
        'slug',
        'store_code',
        'status',
        'plan_code',
        'billing_exempt_until_plan_change',
        'billing_grandfathered_at',
        'onboarding_status',
        'primary_locale',
        'primary_currency_code',
        'timezone',
        'country_code',
        'contact_email',
        'contact_phone',
        'logo_media_id',
        'favicon_media_id',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'launched_at',
        'suspended_at',
    ];

    protected $casts = [
        'id'                    => 'integer',
        'uuid'                  => 'string',
        'name'                  => 'string',
        'legal_name'            => 'string',
        'slug'                  => 'string',
        'store_code'            => 'string',
        'status'                => 'string',
        'plan_code'             => 'string',
        'billing_exempt_until_plan_change' => 'boolean',
        'billing_grandfathered_at' => 'datetime',
        'onboarding_status'     => 'string',
        'primary_locale'        => 'string',
        'primary_currency_code' => 'string',
        'timezone'              => 'string',
        'country_code'          => 'string',
        'contact_email'         => 'string',
        'contact_phone'         => 'string',
        'logo_media_id'         => 'integer',
        'favicon_media_id'      => 'integer',
        'created_by_user_id'    => 'integer',
        'approved_by_user_id'   => 'integer',
        'approved_at'           => 'datetime',
        'launched_at'           => 'datetime',
        'suspended_at'          => 'datetime',
        'deleted_at'            => 'datetime',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(TenantMember::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', config('tenancy.active_tenant_statuses', ['active']));
    }
}
