<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnReason extends Model
{
    use BelongsToTenant, HasFactory;
    protected $fillable = ['tenant_id', 'title', 'status', 'details'];

    protected $casts = [
        'id'        => 'integer',
        'tenant_id' => 'integer',
        'title'     => 'string',
        'status'    => 'integer',
        'details'   => 'string'
    ];

    public function return_and_refunds(){
        return $this->hasMany(ReturnAndRefund::class);
    }
}
