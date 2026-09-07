<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatrolPoint extends Model
{
    protected $fillable = [
        'name',
        'location_address',
        'latitude',
        'longitude',
        'radius_meters',
        'qr_code',
        'photo',
        'description',
        'status',
    ];

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

    // Relasi ke QR codes
    public function qrCodes()
    {
        return $this->hasMany(PatrolPointQr::class);
    }
}