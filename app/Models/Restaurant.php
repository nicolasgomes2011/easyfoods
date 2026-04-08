<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'phone',
        'email',
        'logo',
        'cover_image',
        'address_street',
        'address_number',
        'address_complement',
        'address_neighborhood',
        'address_city',
        'address_state',
        'address_zip',
        'is_open',
        'is_active',
        'accepts_delivery',
        'accepts_pickup',
        'min_order_minutes',
        'max_order_minutes',
    ];

    protected $casts = [
        'is_open'          => 'boolean',
        'is_active'        => 'boolean',
        'accepts_delivery' => 'boolean',
        'accepts_pickup'   => 'boolean',
    ];

    public function operatingHours(): HasMany
    {
        return $this->hasMany(OperatingHour::class)->orderBy('weekday');
    }

    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(StoreSetting::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings->firstWhere('key', $key)?->castValue() ?? $default;
    }
}
