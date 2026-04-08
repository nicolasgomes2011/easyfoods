<?php

namespace App\Models;

use App\Enums\ProductAvailabilityStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'category_id',
        'name',
        'slug',
        'description',
        'image',
        'price',
        'availability_status',
        'sort_order',
        'is_featured',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'availability_status' => ProductAvailabilityStatus::class,
        'sort_order'          => 'integer',
        'is_featured'         => 'boolean',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function addonGroups(): HasMany
    {
        return $this->hasMany(AddonGroup::class)->orderBy('sort_order');
    }

    public function isAvailable(): bool
    {
        return $this->availability_status->isOrderable();
    }

    public function scopeAvailable($query)
    {
        return $query->where('availability_status', ProductAvailabilityStatus::Available->value);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
