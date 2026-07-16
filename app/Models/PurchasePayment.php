<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PurchasePayment extends Model implements HasMedia
{
    use BelongsToTenant, HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'tenant_id',
        'purchase_id',
        'date',
        'reference_no',
        'amount',
        'payment_method'
    ];
    protected $casts = [
        'id'             => 'integer',
        'tenant_id'      => 'integer',
        'purchase_id'    => 'integer',
        'date'           => 'string',
        'reference_no'   => 'string',
        'amount'         => 'decimal:6',
        'payment_method' => 'integer'
    ];

    public function getFileAttribute()
    {
        if (!empty($this->getFirstMediaUrl('purchase_payment'))) {
            $product = $this->getMedia('purchase_payment')->first();
            return $product->getUrl();
        }
    }

    public function purchase(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }
}
