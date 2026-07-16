<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReturnAndRefundProduct extends Model
{
    use BelongsToTenant;

    protected $table = "return_and_refund_products";
    protected $fillable = ['tenant_id', 'return_and_refund_id', 'product_id', 'variation_id', 'variation_names', 'quantity', 'price', 'total', 'return_price', 'user_id'];
    protected $casts = [
        'id'                   => 'integer',
        'tenant_id'            => 'integer',
        'return_and_refund_id' => 'integer',
        'product_id'           => 'integer',
        'variation_id'         => 'integer',
        'variation_names'      => 'string',
        'quantity'             => 'integer',
        'price'                => 'decimal:6',
        'total'                => 'decimal:6',
        'return_price'         => 'decimal:6',
        'user_id'              => 'integer'
    ];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
