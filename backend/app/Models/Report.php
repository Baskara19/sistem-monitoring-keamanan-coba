<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
    'patrol_log_id',
    'report_type',
    'title',
    'description',
    'photo',
    'review_status',
    'reviewed_at',
    'reviewed_by',
];

    // Relasi ke patrol log
    public function patrolLog()
    {
        return $this->belongsTo(PatrolLog::class);
    }
    public function reviewer()
{
    return $this->belongsTo(User::class, 'reviewed_by');
}
}