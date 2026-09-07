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

    protected $appends = ['shift_label'];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function satpam()
    {
        return $this->belongsTo(Satpam::class);
    }

    public function patrolPoint()
    {
        return $this->belongsTo(PatrolPoint::class);
    }

    /**
     * Kategori shift (Pagi/Siang/Malam) diturunkan dari jam mulai.
     * < 10:00 = Pagi, 10:00-12:59 = Siang, >= 13:00 = Malam.
     */
    public function getShiftLabelAttribute(): string
    {
        if (! $this->shift_start) {
            return '-';
        }

        $hour = (int) substr($this->shift_start, 0, 2);

        if ($hour < 10) {
            return 'Pagi';
        }

        if ($hour < 13) {
            return 'Siang';
        }

        return 'Malam';
    }
}