<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatrolLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'satpam_id',
        'patrol_point_id',
        'schedule_detail_id',
        'scan_time',
        'latitude',
        'longitude',
        'distance_from_point',
        'scan_status',
        'note',
    ];

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

    // Relasi ke schedule detail
    public function scheduleDetail()
    {
        return $this->belongsTo(ScheduleDetail::class);
    }

    // Relasi ke report
    public function report()
    {
        return $this->hasOne(Report::class);
    }

    // Relasi ke skip reason
    public function skipReason()
    {
        return $this->hasOne(SkipReason::class);
    }
}