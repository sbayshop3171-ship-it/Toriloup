<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'group_key',
        'setting_key',
        'setting_value',
        'value_type',
        'is_encrypted',
        'updated_by_user_id',
    ];

    protected $casts = [
        'id'                 => 'integer',
        'tenant_id'          => 'integer',
        'group_key'          => 'string',
        'setting_key'        => 'string',
        'setting_value'      => 'string',
        'value_type'         => 'string',
        'is_encrypted'       => 'boolean',
        'updated_by_user_id' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
