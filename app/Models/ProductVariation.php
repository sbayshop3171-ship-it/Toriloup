<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Enums\Status;
use Illuminate\Database\Eloquent\Model;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProductVariation extends  Model implements HasMedia
{
    use BelongsToTenant, HasRecursiveRelationships, InteractsWithMedia;
    protected $table = "product_variations";
    protected $fillable = [
        'tenant_id',
        'product_id',
        'product_attribute_id',
        'product_attribute_option_id',
        'price',
        'sku',
        'parent_id',
        'order'
    ];

    protected $casts = [
        'id'                           => 'integer',
        'tenant_id'                    => 'integer',
        'product_id'                   => 'integer',
        'product_attribute_id'         => 'integer',
        'product_attribute_option_id'  => 'integer',
        'price'                        => 'decimal:6',
        'sku'                          => 'string',
        'parent_id'                    => 'integer',
        'order'                        => 'integer'
    ];

    public function getMediaUrlAttribute(): string
    {
        if (!empty($this->getFirstMediaUrl('product-variation-barcode'))) {
            return asset($this->getFirstMediaUrl('product-variation-barcode'));
        }
        return '';
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productAttribute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class);
    }

    public function productAttributeOption(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductAttributeOption::class);
    }

    public function stocks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Stock::class, 'item');
    }

    public function stockItems(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->stocks()->where('status', Status::ACTIVE);
    }
}
