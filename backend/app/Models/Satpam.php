<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Satpam extends Model
{
    protected $fillable = [
        'user_id',
        'employee_code',
        'badge_number',
        'phone',
        'photo',
        'status',
    ];

    // Relasi ke user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke patrol logs
    public function patrolLogs()
    {
        return $this->hasMany(PatrolLog::class);
    }

    // Relasi ke schedule details
    public function scheduleDetails()
    {
        return $this->hasMany(ScheduleDetail::class);
    }
}