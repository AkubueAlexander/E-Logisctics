<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierOption extends Model
{
    protected $fillable = [
        'modifier_group_id',
        'name',
        'price_minor_unit',
        'is_available'
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'price_minor_unit' => 'integer',
    ];

    public function modifierGroup(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class);
    }
}
