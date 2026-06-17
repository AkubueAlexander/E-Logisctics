<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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



    // In app/Models/Order.php

    public function syncStatus(): void
    {
        // Reload relations to get the latest statuses
        $this->load('subOrders');
        $subOrders = $this->subOrders;

        // 1. If any are cancelled, we have a partial or total issue
        if ($subOrders->every(fn($s) => $s->status === 'cancelled')) {
            $this->update(['status' => 'cancelled']);
        }
        // 2. If at least one is accepted and others are not cancelled, it's active
        elseif ($subOrders->contains('status', 'accepted')) {
            $this->update(['status' => 'accepted']);
        }
        // 3. If all are delivered
        elseif ($subOrders->every(fn($s) => $s->status === 'delivered')) {
            $this->update(['status' => 'delivered']);
        }
        // 4. Default: processing
        else {
            $this->update(['status' => 'processing']);
        }
    }
}
