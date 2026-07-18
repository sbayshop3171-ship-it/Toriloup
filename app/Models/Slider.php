<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Image\Enums\CropPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
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

    public function getImageAttribute(): ?string
    {
        $slider = $this->getMedia('slider')->last();

        if ($slider instanceof Media) {
            return $this->sliderMediaUrl($slider);
        }

        if ($this->tenant_id !== null) {
            return null;
        }

        return asset('images/default/slider.png');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('cover')->fit(Fit::Fill, 1689, 600)->keepOriginalImageFormat()->sharpen(10);
    }

    private function sliderMediaUrl(Media $slider): ?string
    {
        if ($slider->hasGeneratedConversion('cover') && $this->mediaFileExists($slider, 'cover')) {
            return $slider->getUrl('cover');
        }

        if ($this->mediaFileExists($slider)) {
            return $slider->getUrl();
        }

        return $this->tenant_id === null ? asset('images/default/slider.png') : null;
    }

    private function mediaFileExists(Media $media, string $conversion = ''): bool
    {
        try {
            $disk = $conversion === '' ? $media->disk : ($media->conversions_disk ?: $media->disk);

            return Storage::disk($disk)->exists($media->getPathRelativeToRoot($conversion));
        } catch (\Throwable) {
            return false;
        }
    }
}
