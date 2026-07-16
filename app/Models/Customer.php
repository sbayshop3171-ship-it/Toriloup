<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Customer extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'legacy_user_id',
        'uuid',
        'name',
        'email',
        'phone',
        'country_code',
        'password',
        'status',
        'is_guest',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'id'                => 'integer',
        'tenant_id'         => 'integer',
        'legacy_user_id'    => 'integer',
        'uuid'              => 'string',
        'name'              => 'string',
        'email'             => 'string',
        'phone'             => 'string',
        'country_code'      => 'string',
        'password'          => 'hashed',
        'status'            => 'integer',
        'is_guest'          => 'boolean',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function legacyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'legacy_user_id');
    }
}
