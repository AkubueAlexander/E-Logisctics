<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubOrder extends Model
{
    protected $fillable = [
        'order_id',
        'store_id',
        'status',
        'subtotal_minor_unit',
        'platform_commission_minor_unit',
        'estimated_prep_time_minutes',
    ];

    /**
     * Get the parent order checkout context.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the store fulfilling this specific part of the transaction.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the individual physical items ordered from this vendor.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the specific ledger entries tied to this sub-order's payouts/commissions.
     */
    public function ledgers(): HasMany
    {
        return $this->hasMany(Ledger::class);
    }
}
