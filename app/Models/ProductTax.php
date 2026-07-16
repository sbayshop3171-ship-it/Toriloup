<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class ProductTax extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = "product_taxes";
    protected $fillable    = ['tenant_id', 'product_id', 'tax_id'];
    protected $casts = [
        'id'         => 'integer',
        'tenant_id'  => 'integer',
        'product_id' => 'integer',
        'tax_id'     => 'integer',
    ];

    public function tax(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
