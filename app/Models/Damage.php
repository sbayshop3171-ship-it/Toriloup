<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Damage extends Model implements HasMedia
{
    use BelongsToTenant, HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'tenant_id',
        'date',
        'reference_no',
        'subtotal',
        'tax',
        'discount',
        'total',
        'note'
    ];

    protected $casts = [
        'id'            => 'integer',
        'tenant_id'     => 'integer',
        'date'          => 'datetime',
        'reference_no'  => 'string',
        'subtotal'      => 'decimal:6',
        'tax'           => 'decimal:6',
        'discount'      => 'decimal:6',
        'total'         => 'decimal:6',
        'note'          => 'string'
    ];

    public function stocks(): \Illuminate\Database\Eloquent\Relations\morphMany
    {
        return $this->morphMany(Stock::class, 'model');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    public function getFileAttribute()
    {
        if (!empty($this->getFirstMediaUrl('damage'))) {
            $product = $this->getMedia('damage')->first();
            return $product->getUrl();
        }
    }
}
