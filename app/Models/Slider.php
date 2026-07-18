<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Image\Enums\CropPosition;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Slider extends Model implements HasMedia
{
    use BelongsToTenant;
    use InteractsWithMedia;

    protected $table = "sliders";
    protected $fillable = ['tenant_id', 'title', 'link', 'description', 'status'];
    protected $casts = [
        'tenant_id'    => 'integer',
        'id'          => 'integer',
        'title'       => 'string',
        'description' => 'string',
        'status'      => 'integer',
        'link'        => 'string',
    ];

    public function getImageAttribute(): string
    {
        if (!empty($this->getFirstMediaUrl('slider'))) {
            $slider = $this->getMedia('slider')->last();
            return $slider->getUrl('cover');
        }
        return asset('images/default/slider.png');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('cover')->fit(Fit::Fill, 1689, 600)->keepOriginalImageFormat()->sharpen(10);
    }
}
