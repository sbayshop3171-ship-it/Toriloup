<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformPlanPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'billing_interval',
        'price_amount',
    ];

    protected $casts = [
        'id' => 'integer',
        'plan_id' => 'integer',
        'billing_interval' => 'string',
        'price_amount' => 'decimal:2',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformPlan::class, 'plan_id');
    }
}
