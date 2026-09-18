<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'username',
        'nipkwt',
        'password',
        'role',
        'tim',
        'phone',
        'location',
        'location_id',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relasi ke satpam
    public function satpam()
    {
        return $this->hasOne(Satpam::class);
    }

    // Relasi ke supervisor
    public function supervisor()
    {
        return $this->hasOne(Supervisor::class);
    }
public function masterLocation(): BelongsTo
{
    return $this->belongsTo(Location::class, 'location_id');
}
}