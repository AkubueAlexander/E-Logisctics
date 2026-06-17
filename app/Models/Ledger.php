<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ledger extends Model
{
    protected $fillable = [
        'order_id',
        'sub_order_id',
        'transaction_type',
        'store_id',
        'user_id',
        'amount_minor_unit',
        'currency_code',
        'status',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    /**
     * If this is a vendor payout, get the target store model.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * If this is a courier/driver payout, get the target driver user model.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
