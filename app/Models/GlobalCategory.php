<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class GlobalCategory extends Model
{
    use HasFactory;



    protected $fillable = [
        'name',
        'slug',
        'icon_url',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Boot hook to automatically manage slugs when creating/updating.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($category) {
            $category->slug = Str::slug($category->name);
        });

        static::updating(function ($category) {
            $category->slug = Str::slug($category->name);
        });
    }

    /**
     * Relationship: A global category tag belongs to many Stores.
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'global_category_store')
            ->withTimestamps();
    }
}
