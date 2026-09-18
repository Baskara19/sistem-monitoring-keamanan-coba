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
     * Kategori shift (Pagi/Siang/Malam) diturunkan dari jam mulai, sesuai
     * skema shift baku: Pagi 06:00-14:00, Siang 14:00-22:00,
     * Malam 22:00-06:00 (lintas tengah malam).
     */
    public function getShiftLabelAttribute(): string
    {
        if (! $this->shift_start) {
            return '-';
        }

        $hour = (int) substr($this->shift_start, 0, 2);

        if ($hour >= 22 || $hour < 6) {
            return 'Malam';
        }

        if ($hour < 14) {
            return 'Pagi';
        }

        return 'Siang';
    }
}