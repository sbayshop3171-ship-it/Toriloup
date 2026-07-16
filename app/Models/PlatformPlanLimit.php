<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformPlanLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'limit_key',
        'limit_value',
        'is_unlimited',
    ];

    protected $casts = [
        'id' => 'integer',
        'plan_id' => 'integer',
        'limit_key' => 'string',
        'limit_value' => 'integer',
        'is_unlimited' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformPlan::class, 'plan_id');
    }
}
