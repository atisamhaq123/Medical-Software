<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalNote extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'signed_at' => 'datetime'];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
