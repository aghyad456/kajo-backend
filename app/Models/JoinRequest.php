<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JoinRequest extends Model
{
    protected $fillable = [
        'user_id',
        'requested_role',
        'status',
        'document_path',
    ];

   public function user()
    {
        return $this->belongsTo(User::class);
    }

}
