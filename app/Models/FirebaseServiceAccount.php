<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FirebaseServiceAccount extends Model
{
    protected $table = 'firebase_service_accounts';

    protected $fillable = ['encrypted_json'];

    protected $hidden = ['encrypted_json'];
}
