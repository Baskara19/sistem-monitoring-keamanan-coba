<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'supervisor_id',
        'katim_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'supervisor_id' => 'integer',
        'katim_id'      => 'integer',
    ];

    // Relasi ke supervisor
    public function supervisor()
    {
        return $this->belongsTo(Supervisor::class);
    }

    // Relasi ke KAT / Katim (Ketua Regu)
    public function katim()
    {
        return $this->belongsTo(Satpam::class, 'katim_id');
    }

    // Relasi ke schedule details
    public function scheduleDetails()
    {
        return $this->hasMany(ScheduleDetail::class);
    }
}