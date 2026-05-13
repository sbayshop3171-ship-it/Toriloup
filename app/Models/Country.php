<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $table ="countries";

    protected $fillable = ["name","code","currency_code","currency_symbol","status"];

    protected $casts = [
        'id'              => 'integer',
        'name'            => 'string',
        'code'            => 'string',
        'currency_code'   => 'string',
        'currency_symbol' => 'string',
        'status'          => 'integer'
    ];
}
