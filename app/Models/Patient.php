<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'allergies' => 'encrypted', 'conditions' => 'encrypted'];
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function notes()
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function careTeam()
    {
        return $this->belongsToMany(User::class);
    }

    public function getInitialsAttribute(): string
    {
        return mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1);
    }

    public function getNameAttribute(): string
    {
        return $this->first_name.' '.$this->last_name;
    }
}
