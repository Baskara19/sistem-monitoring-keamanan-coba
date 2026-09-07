<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'supervisor_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'status',
    ];

    // Relasi ke supervisor
    public function supervisor()
    {
        return $this->belongsTo(Supervisor::class);
    }

    // Relasi ke schedule details
    public function scheduleDetails()
    {
        return $this->hasMany(ScheduleDetail::class);
    }
}