<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Outlet extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'email', 'phone', 'country_code', 'latitude', 'longitude', 'city', 'state', 'zip_code', 'address', 'status'];
    protected $casts = [
        'tenant_id'    => 'integer',
        'id'           => 'integer',
        'name'         => 'string',
        'email'        => 'string',
        'phone'        => 'string',
        'country_code' => 'string',
        'latitude'     => 'string',
        'longitude'    => 'string',
        'city'         => 'string',
        'state'        => 'string',
        'zip_code'     => 'string',
        'address'      => 'string',
        'status'       => 'integer',
    ];
}
