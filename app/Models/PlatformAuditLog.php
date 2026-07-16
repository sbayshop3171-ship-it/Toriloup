<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'actor_scope',
        'tenant_id',
        'action_code',
        'entity_type',
        'entity_id',
        'old_values_json',
        'new_values_json',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'actor_user_id' => 'integer',
        'tenant_id' => 'integer',
        'entity_id' => 'integer',
        'old_values_json' => 'array',
        'new_values_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
