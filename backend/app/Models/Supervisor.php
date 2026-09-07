<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supervisor extends Model
{
    protected $fillable = [
        'user_id',
        'employee_code',
        'phone',
        'status',
    ];

    // Relasi ke user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke schedules
    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}