<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkipReason extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'patrol_log_id',
        'reason',
        'photo',
        'review_status',
    ];

    // Relasi ke patrol log
    public function patrolLog()
    {
        return $this->belongsTo(PatrolLog::class);
    }
}