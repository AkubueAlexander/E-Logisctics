<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'sub_order_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price_minor_unit',
        'total_price_minor_unit',
        'customizations',
        'special_instructions',
    ];

    /**
     * Senior Guard: Cast JSONB to a clean native array automatically.
     */
    protected $casts = [
        'customizations' => 'array',
    ];

    /**
     * Get the vendor child sub-order this item belongs to.
     */
    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    /**
     * Optional link back to live catalog data.
     * Can return null if the store deletes the product later.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
