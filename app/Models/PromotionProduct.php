<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionProduct extends Model
{
    use BelongsToTenant, HasFactory;
    protected $table = "promotion_products";
    protected $fillable = ['tenant_id', 'promotion_id', 'product_id'];
    protected $casts = [
        'tenant_id'    => 'integer',
        'id'           => 'integer',
        'promotion_id' => 'integer',
        'product_id'   => 'integer',
    ];

    public function promotion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promotion_id', 'id');
    }
    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id')->withTrashed();
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'promotion_products', 'promotion_id', 'product_id');
    }
}
