<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatrolHandover extends Model
{
    protected $fillable = [
        'from_satpam_id',
        'to_satpam_id',
        'schedule_detail_id',
        'patrol_point_id',
        'patrol_round',
        'handover_date',
        'reason',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'patrol_round'  => 'integer',
        'handover_date' => 'date',
        'responded_at'  => 'datetime',
    ];

    // Satpam yang mengajukan handover (pemohon)
    public function fromSatpam()
    {
        return $this->belongsTo(Satpam::class, 'from_satpam_id');
    }

    // Satpam penerima handover
    public function toSatpam()
    {
        return $this->belongsTo(Satpam::class, 'to_satpam_id');
    }

    // Detail jadwal yang dioper
    public function scheduleDetail()
    {
        return $this->belongsTo(ScheduleDetail::class, 'schedule_detail_id');
    }

    // Titik patroli yang dioper
    public function patrolPoint()
    {
        return $this->belongsTo(PatrolPoint::class, 'patrol_point_id');
    }

    // Log patroli hasil eksekusi handover ini
    public function patrolLog()
    {
        return $this->hasOne(PatrolLog::class, 'patrol_handover_id');
    }
}
