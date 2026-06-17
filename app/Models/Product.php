<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'store_id',
        'category_id',
        'name',
        'description',
        'price_minor_unit',
        'currency_code',
        'stock_quantity',
        'is_available',
        'version',
        'attributes',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'attributes' => 'array', // Maps perfectly to PostgreSQL JSONB
        'price_minor_unit' => 'integer',
        'stock_quantity' => 'integer',
        'version' => 'integer',
    ];

    /**
     * Get the store that owns this product.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the menu category this product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
