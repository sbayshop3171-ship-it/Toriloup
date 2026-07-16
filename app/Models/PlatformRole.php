<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlatformRole extends Model
{
    use HasFactory;

    protected $table = 'platform_roles';

    protected $fillable = [
        'code',
        'name',
        'scope',
        'is_system',
    ];

    protected $casts = [
        'id'        => 'integer',
        'code'      => 'string',
        'name'      => 'string',
        'scope'     => 'string',
        'is_system' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(PlatformPermission::class, 'platform_role_permissions', 'role_id', 'permission_id');
    }
}
