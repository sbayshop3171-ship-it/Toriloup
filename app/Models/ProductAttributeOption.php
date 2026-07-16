<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAttributeOption extends Model
{
    use BelongsToTenant, HasFactory;
    protected $table = "product_attribute_options";
    protected $fillable = ['tenant_id', 'product_attribute_id', 'name'];
    protected $casts = [
        'id'                   => 'integer',
        'tenant_id'            => 'integer',
        'product_attribute_id' => 'integer',
        'name'                 => 'string'
    ];
}
