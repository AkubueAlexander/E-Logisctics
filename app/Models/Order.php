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

    /**
     * Strict Consensus State Machine Engine
     * Evaluates child sub-order states to accurately advance the parent lifecycle.
     */
    public function syncStatus(): void
    {
        // Reload relations to clear out internal cached memory state
        $this->load('subOrders');
        $subOrders = $this->subOrders;

        // Filter out cancelled sub-orders to isolate only surviving merchant legs
        $activeSubOrders = $subOrders->filter(fn($s) => $s->status !== 'cancelled');

        // 1. Total Failure: Every single store declined or timed out
        if ($subOrders->every(fn($s) => $s->status === 'cancelled')) {
            $this->update(['status' => 'cancelled']);
        }

        // 2. Strict Consensus: All active, surviving merchants have hit accept
        elseif ($activeSubOrders->isNotEmpty() && $activeSubOrders->every(fn($s) => $s->status === 'accepted')) {
            $this->update(['status' => 'accepted']);

            // Fire the dispatch event to alert InitializeDeliveryMission to spin up a rider track
            event(new \App\Events\OrderReadyForDispatch($this));
        }

        // 3. Complete Fulfillment: Every single child leg was successfully dropped off
        elseif ($subOrders->every(fn($s) => $s->status === 'delivered')) {
            $this->update(['status' => 'delivered']);
        }

        // 4. Default: Mixed states (e.g., one store accepted, one still pending) stay safe
        else {
            $this->update(['status' => 'processing']);
        }
    }
}
