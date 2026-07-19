<?php

namespace App\Models;

use Spatie\Image\Enums\Fit;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Image\Enums\CropPosition;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Throwable;


class User extends Authenticatable implements HasMedia
{
    use InteractsWithMedia;
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $table = "users";
    protected $dates = ["deleted_at"];
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'phone',
        'country_code',
        'is_guest',
        'status',
        'email_verified_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */

    protected $casts = [
        'id'                => 'integer',
        'name'              => 'string',
        'email'             => 'string',
        'password'          => 'hashed',
        'username'          => 'string',
        'phone'             => 'string',
        'country_code'      => 'string',
        'is_guest'          => 'integer',
        'status'            => 'integer',
        'email_verified_at' => 'datetime',
    ];

    public function getImageAttribute(): string
    {
        return $this->profileMediaUrl();
    }

    public function getFirstNameAttribute(): string
    {
        $name = explode(' ', $this->name, 2);
        return $name[0];
    }

    public function getLastNameAttribute(): string
    {
        $name = explode(' ', $this->name, 2);
        return !empty($name[1]) ? $name[1] : '';
    }

    public function getThumbAttribute(): string
    {
        return $this->profileMediaUrl(['thumb']);
    }

    private function profileMediaUrl(array $conversionNames = []): string
    {
        $profile = $this->getMedia('profile')->last();

        if (!$profile) {
            return $this->defaultProfileImage();
        }

        foreach ($conversionNames as $conversionName) {
            if (
                $profile->hasGeneratedConversion($conversionName)
                && $this->mediaFileExists($profile, $conversionName)
            ) {
                return $profile->getUrl($conversionName);
            }
        }

        if ($this->mediaFileExists($profile)) {
            return $profile->getUrl();
        }

        return $this->defaultProfileImage();
    }

    private function mediaFileExists(Media $media, string $conversionName = ''): bool
    {
        try {
            $disk = $conversionName === ''
                ? $media->disk
                : ($media->conversions_disk ?: $media->disk);

            return Storage::disk($disk)->exists($media->getPathRelativeToRoot($conversionName));
        } catch (Throwable) {
            return true;
        }
    }

    private function defaultProfileImage(): string
    {
        return asset('images/required/profile.png');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit(Fit::Fill, 338, 338)->keepOriginalImageFormat()->sharpen(10);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class, 'user_id', 'id');
    }

    public function addresses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function tenantMembers(): HasMany
    {
        return $this->hasMany(TenantMember::class);
    }


    public function getMyRoleAttribute()
    {
        return $this->roles->pluck('id', 'id')->first();
    }

    public function getrole(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Role::class, 'id', 'myrole');
    }
    public function returnOrders()
    {
        $this->hasMany(ReturnOrder::class, 'user_id', 'id');
    }
}
