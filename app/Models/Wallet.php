<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Wallet extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'owner_type',
        'balance_minor_unit',
        'currency',
    ];

    /**
     * Get the parent owner model (User or Store).
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
