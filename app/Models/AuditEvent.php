<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
