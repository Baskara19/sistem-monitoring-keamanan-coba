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
        'patrol_round',  // Putaran ke-berapa dalam shift (1–4)
        'delegated_from_satpam_id',
        'patrol_handover_id',
        'note',
    ];

    protected $casts = [
        'patrol_round' => 'integer',
        'delegated_from_satpam_id' => 'integer',
        'patrol_handover_id' => 'integer',
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

    // Relasi ke satpam yang diwakili (handover)
    public function delegatedFromSatpam()
    {
        return $this->belongsTo(Satpam::class, 'delegated_from_satpam_id');
    }

    // Relasi ke record handover
    public function handover()
    {
        return $this->belongsTo(PatrolHandover::class, 'patrol_handover_id');
    }
}