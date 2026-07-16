<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantFeatureFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'feature_code',
        'status',
        'source',
        'updated_by_user_id',
    ];

    protected $casts = [
        'id'                 => 'integer',
        'tenant_id'          => 'integer',
        'feature_code'       => 'string',
        'status'             => 'boolean',
        'source'             => 'string',
        'updated_by_user_id' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
