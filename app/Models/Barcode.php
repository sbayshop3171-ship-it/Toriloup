<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barcode extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
    ];
}
