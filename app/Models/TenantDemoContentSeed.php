<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantDemoContentSeed extends Model
{
    protected $fillable = [
        'tenant_id',
        'source_type',
        'source_id',
        'target_type',
        'target_id',
    ];

    protected $casts = [
        'id' => 'integer',
        'tenant_id' => 'integer',
        'source_id' => 'integer',
        'target_id' => 'integer',
    ];
}
