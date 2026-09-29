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

    // Permintaan handover yang diajukan oleh satpam ini
    public function handoversSent()
    {
        return $this->hasMany(PatrolHandover::class, 'from_satpam_id');
    }

    // Permintaan handover yang ditujukan kepada satpam ini
    public function handoversReceived()
    {
        return $this->hasMany(PatrolHandover::class, 'to_satpam_id');
    }
}