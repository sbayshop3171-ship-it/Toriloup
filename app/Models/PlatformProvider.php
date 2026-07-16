<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_type',
        'provider_code',
        'name',
        'status',
        'config_json',
    ];

    protected $casts = [
        'id' => 'integer',
        'provider_type' => 'string',
        'provider_code' => 'string',
        'name' => 'string',
        'status' => 'boolean',
        'config_json' => 'array',
    ];
}
