<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleDetail extends Model
{
    protected $fillable = [
        'schedule_id',
        'satpam_id',
        'patrol_point_id',
        'shift_start',
        'shift_end',
        'sequence_order',
    ];

    // Relasi ke schedule
    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    // Relasi ke satpam
    public function satpam()
    {
        return $this->belongsTo(Satpam::class);
    }

    // Relasi ke patrol point
    public function patrolPoint()
    {
        return $this->belongsTo(PatrolPoint::class);
    }

    // Relasi ke patrol logs
    public function patrolLogs()
    {
        return $this->hasMany(PatrolLog::class);
    }
}