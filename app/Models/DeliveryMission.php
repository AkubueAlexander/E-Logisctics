<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryMission extends Model
{
    protected $fillable = ['order_id', 'driver_id', 'status', 'delivery_fee_minor_unit'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function pings(): HasMany
    {
        return $this->hasMany(MissionPing::class);
    }
}
