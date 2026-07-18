<?php

namespace App\Models;

use App\Enums\Status;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use BelongsToTenant;

    protected $table = "taxes";
    protected $fillable = ['tenant_id', 'name', 'code', 'tax_rate', 'status'];
    protected $casts = [
        'tenant_id' => 'integer',
        'id'       => 'integer',
        'name'     => 'string',
        'code'     => 'string',
        'tax_rate' => 'string',
        'status'   => 'integer',
    ];

}
