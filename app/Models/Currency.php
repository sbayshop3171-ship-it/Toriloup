<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use BelongsToTenant;

    protected $table = "currencies";
    protected $fillable = ['tenant_id', 'name', 'symbol', 'code', 'is_cryptocurrency', 'exchange_rate'];

    protected $casts = [
        'tenant_id'          => 'integer',
        'id'                => 'integer',
        'name'              => 'string',
        'symbol'            => 'string',
        'code'              => 'string',
        'is_cryptocurrency' => 'integer',
        'exchange_rate'     => 'decimal:6',
    ];
}
