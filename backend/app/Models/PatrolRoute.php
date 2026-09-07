<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatrolRoute extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    public function points()
    {
        return $this->hasMany(PatrolRoutePoint::class)->orderBy('sequence_order');
    }
}
