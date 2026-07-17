<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderArea extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = "order_areas";
    protected $fillable    = ['tenant_id', 'country', 'state', 'city', 'shipping_cost', 'status'];
    protected $casts = [
        'id'            => 'integer',
        'tenant_id'     => 'integer',
        'country'       => 'string',
        'state'         => 'string',
        'city'          => 'string',
        'shipping_cost' => 'string',
        'status'        => 'integer',
    ];
}
