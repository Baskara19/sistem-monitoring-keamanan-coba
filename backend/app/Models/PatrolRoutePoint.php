<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatrolRoutePoint extends Model
{
    protected $fillable = [
        'patrol_route_id',
        'patrol_point_id',
        'sequence_order',
    ];

    public function route()
    {
        return $this->belongsTo(PatrolRoute::class, 'patrol_route_id');
    }

    public function patrolPoint()
    {
        return $this->belongsTo(PatrolPoint::class);
    }
}
