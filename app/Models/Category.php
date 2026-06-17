<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;


class Category extends Model
{
    protected $fillable = [
        'name', 'slug', 'parent_id', 'image_path', 'store_id', 'sort_order', 'is_active'
    ];

    protected static function boot(): void
    {
        parent::boot();
        // Auto-generate slug if not provided, appending store_id to ensure uniqueness if needed
        static::creating(function ($category) {
            if (!$category->slug) {

                $category->slug = Str::slug($category->name) . '-' . $category->store_id;
            }
        });
    }

    // --- Relationships ---

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function taggedStores(): BelongsToMany
    {
        // For Global Categories tagging many restaurants
        return $this->belongsToMany(Store::class, 'category_store');
    }

    // --- Scopes ---

    public function scopeGlobal(Builder $query): void
    {
        $query->whereNull('store_id');
    }

    public function scopeMenu(Builder $query): void
    {
        $query->whereNotNull('store_id');
    }
}
