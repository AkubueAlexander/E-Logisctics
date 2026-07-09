<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'idempotency_key',
        'customer_id',
        'driver_id',
        'snapshot_delivery_address',
        'snapshot_delivery_latitude',
        'snapshot_delivery_longitude',
        'status',
        'transaction_reference',
        'payment_status',
        'subtotal_minor_unit',
        'delivery_fee_minor_unit',
        'service_fee_minor_unit',
        'total_minor_unit',
        'currency_code',
    ];

    protected $casts = [
        'snapshot_delivery_latitude' => 'decimal:8',
        'snapshot_delivery_longitude' => 'decimal:8',
        'version' => 'integer',
    ];

    /**
     * Get the customer who placed the order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the driver assigned to deliver this bundled order.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the store-specific sub-orders (child baskets).
     */
    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class);
    }

    /**
     * Get all financial ledger entries mapped to this checkout.
     */
    public function ledgers(): HasMany
    {
        return $this->hasMany(Ledger::class);
    }

    public function stateTransitions(): HasMany
    {
        return $this->hasMany(OrderStateTransition::class);
    }

    public function deliveryMission(): HasMany
    {
        return $this->hasMany(DeliveryMission::class);
    }

    public function latestDeliveryMission(): HasOne
    {
        return $this->hasOne(DeliveryMission::class)->latestOfMany('id');
    }




}
