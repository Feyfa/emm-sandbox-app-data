<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserIdentity extends Model
{
    protected $table = 'user_identities';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_uid',
        'email',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
