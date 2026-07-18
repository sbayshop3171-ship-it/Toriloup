<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Page extends Model implements HasMedia
{
    use BelongsToTenant;
    use InteractsWithMedia;

    protected $table = "pages";
    protected $fillable = ['tenant_id', 'title', 'slug', 'description', 'menu_section_id', 'menu_template_id', 'status'];
    protected $casts = [
        'tenant_id'         => 'integer',
        'id'               => 'integer',
        'title'            => 'string',
        'slug'             => 'string',
        'description'      => 'string',
        'menu_section_id'  => 'integer',
        'menu_template_id' => 'integer',
        'status'           => 'integer',
    ];

    public function getImageAttribute(): string
    {
        if (!empty($this->getFirstMediaUrl('page-image'))) {
            return asset($this->getFirstMediaUrl('page-image'));
        }
        return '';
    }

    public function menuSection()
    {
        return $this->belongsTo(MenuSection::class, 'menu_section_id', 'id');
    }
}
