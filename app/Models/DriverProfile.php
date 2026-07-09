<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class DriverProfile extends Model
{
    use HasFactory;



    /**
     * The attributes that are mass assignable based on your PostgreSQL schema.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'vehicle_type',
        'license_plate',
        'status',
        'current_latitude',
        'current_longitude',
        'last_location_update',
    ];

    /**
     * The attributes that should be cast to native types.
     * Ensures coordinates come back as floats and tracking timestamps as Carbon instances.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'last_location_update' => 'datetime',
    ];

    /**
     * Inverse relationship link: Get the core user account that owns this driver profile.
     * Maps to your schema's foreign key constraint 'driver_profiles_user_id_foreign'.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function missionPings(): HasMany
    {
        return $this->hasMany(MissionPing::class, 'driver_id');
    }

    /**
     * Scope a query to only include verified, active, and available drivers within a specific distance.
     * Distance parameter is passed in kilometers (e.g., 5 for 5km).
     */
    public function scopeWithinRadius(Builder $query, float $latitude, float $longitude, float $radiusKm): Builder
    {
        return $query->where('verification_status', 'verified')
            ->where('availability_status', 'available')
            ->whereNotNull('location')
            // Protect against dead apps: Ensure driver has pinged coordinates within the last 5 minutes
//            ->where('last_location_update', '>=', now()->subMinutes(5))
            ->whereRaw(
                "ST_DistanceSphere(location, ST_GeomFromText(?, 4326)) <= ?",
                ["POINT({$longitude} {$latitude})", $radiusKm * 1000]
            );
    }
}
