<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderCoupon extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = "order_coupons";
    protected $fillable = [
        'tenant_id',
        'order_id',
        'coupon_id',
        'user_id',
        'discount'
    ];

    protected $casts = [
        'id'           => 'integer',
        'tenant_id'    => 'integer',
        'order_id'     => 'integer',
        'coupon_id' => 'integer',
        'user_id'      => 'integer',
        'discount' => 'decimal:6'
    ];
}
