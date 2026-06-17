<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;


class Store extends Model
{
    protected $fillable = [
        'name', 'slug', 'logo_url', 'address', 'latitude', 'longitude', 'is_active', 'operating_hours'
    ];

    protected $casts = [
        // Leverages your PostgreSQL jsonb column natively
        'operating_hours' => 'array',
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($store) => $store->slug = Str::slug($store->name));
    }

    public function globalCategories(): BelongsToMany
    {
        return $this->belongsToMany(GlobalCategory::class, 'global_category_store')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Category>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort_order');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')->withPivot('role');
    }
}
