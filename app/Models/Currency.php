<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use BelongsToTenant;

    protected $table = "currencies";
    protected $fillable = [
        'tenant_id',
        'name',
        'symbol',
        'code',
        'minor_unit',
        'is_cryptocurrency',
        'exchange_rate',
        'is_auto_managed',
        'is_enabled',
        'rate_source',
        'rate_synced_at',
        'rate_metadata_json',
    ];

    protected $casts = [
        'tenant_id'          => 'integer',
        'id'                => 'integer',
        'name'              => 'string',
        'symbol'            => 'string',
        'code'              => 'string',
        'minor_unit'        => 'integer',
        'is_cryptocurrency' => 'integer',
        'exchange_rate'     => 'decimal:6',
        'is_auto_managed'   => 'boolean',
        'is_enabled'        => 'boolean',
        'rate_synced_at'    => 'datetime',
        'rate_metadata_json'=> 'array',
    ];
}
