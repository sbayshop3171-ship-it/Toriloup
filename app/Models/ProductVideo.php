<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVideo extends Model
{
    use BelongsToTenant, HasFactory;
    protected $table = "product_videos";
    protected $fillable = ['tenant_id', 'product_id', 'video_provider', 'link'];
    protected $casts = [
        'id'             => 'integer',
        'tenant_id'      => 'integer',
        'product_id'     => 'integer',
        'video_provider' => 'integer',
        'link'           => 'string',
    ];
}
