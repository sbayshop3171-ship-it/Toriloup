<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlatformPermission extends Model
{
    use HasFactory;

    protected $table = 'platform_permissions';

    protected $fillable = [
        'code',
        'name',
        'scope',
        'module',
    ];

    protected $casts = [
        'id'     => 'integer',
        'code'   => 'string',
        'name'   => 'string',
        'scope'  => 'string',
        'module' => 'string',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(PlatformRole::class, 'platform_role_permissions', 'permission_id', 'role_id');
    }
}
