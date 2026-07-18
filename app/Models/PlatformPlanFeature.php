<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformPlanFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'feature_code',
        'display_label',
        'compare_group',
        'feature_type',
        'feature_value',
        'sort_order',
    ];

    protected $casts = [
        'id' => 'integer',
        'plan_id' => 'integer',
        'feature_code' => 'string',
        'display_label' => 'string',
        'compare_group' => 'string',
        'feature_type' => 'string',
        'feature_value' => 'string',
        'sort_order' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformPlan::class, 'plan_id');
    }
}
