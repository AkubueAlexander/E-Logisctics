<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStateTransition extends Model
{
    // Define the table name if it does not follow standard naming conventions
    protected $table = 'order_state_transitions';

    // Disable timestamps as per your requirement
    public $timestamps = false;

    // Allow these fields to be set when calling ->create()
    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'metadata',
        'triggered_by'
    ];

    // Cast metadata to array so it handles JSON automatically
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Link this transition back to the parent order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
