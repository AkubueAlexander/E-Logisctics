<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionPing extends Model
{
    protected $fillable = ['delivery_mission_id', 'driver_id', 'status', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(DeliveryMission::class, 'delivery_mission_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
