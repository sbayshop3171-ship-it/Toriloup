<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Image\Enums\CropPosition;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductSeo extends Model implements HasMedia
{
    use BelongsToTenant;
    use InteractsWithMedia;
    protected $table = "product_seos";
    protected $fillable = ['tenant_id', 'product_id', 'title', 'description', 'meta_keyword'];
    protected $casts = [
        'id'           => 'integer',
        'tenant_id'    => 'integer',
        'product_id'   => 'integer',
        'title'        => 'string',
        'description'  => 'string',
        'meta_keyword' => 'string',
    ];

    public function getThumbAttribute(): string
    {
        if (!empty($this->getFirstMediaUrl('product-seo'))) {
            $brand = $this->getMedia('product-seo')->last();
            return $brand->getUrl('thumb');
        }
        return asset('images/default/seo/thumb.png');
    }

    public function getCoverAttribute(): string
    {
        if (!empty($this->getFirstMediaUrl('product-seo'))) {
            $brand = $this->getMedia('product-seo')->last();
            return $brand->getUrl('cover');
        }
        return asset('images/default/seo/cover.png');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit(Fit::Fill, 112, 72)->keepOriginalImageFormat()->sharpen(10);
        $this->addMediaConversion('cover')->width(600)->keepOriginalImageFormat()->sharpen(10);
    }
}
