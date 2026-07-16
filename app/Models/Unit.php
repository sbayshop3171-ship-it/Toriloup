<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Enums\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends Model
{
    use BelongsToTenant, HasFactory;
    protected $table = "units";
    protected $fillable = ['tenant_id', 'name', 'code', 'status'];
    protected $casts = [
        'id'     => 'integer',
        'tenant_id' => 'integer',
        'name'   => 'string',
        'code'   => 'string',
        'status' => 'integer',
    ];

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class)->where(['status' => Status::ACTIVE]);
    }
}
