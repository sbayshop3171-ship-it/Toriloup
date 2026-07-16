<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAttribute extends Model
{
    use BelongsToTenant, HasFactory;
    protected $table = "product_attributes";
    protected $fillable = ['tenant_id', 'name'];
    protected $casts = [
        'id'     => 'integer',
        'tenant_id' => 'integer',
        'name'   => 'string',
    ];

    public function productAttributeOptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductAttributeOption::class, 'product_attribute_id', 'id');
    }
}
