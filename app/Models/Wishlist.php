<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = "wishlists";
    protected $fillable = ['tenant_id', 'product_id', 'user_id'];
    protected $casts = [
        'id'         => 'integer',
        'tenant_id'  => 'integer',
        'product_id' => 'integer',
        'user_id'    => 'integer',
    ];


    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
