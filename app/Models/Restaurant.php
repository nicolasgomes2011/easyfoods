<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
        'accepts_dine_in',
        'min_order_minutes',
        'max_order_minutes',
    ];

    protected $casts = [
        'is_open'          => 'boolean',
        'is_active'        => 'boolean',
        'accepts_delivery' => 'boolean',
        'accepts_pickup'   => 'boolean',
        'accepts_dine_in'  => 'boolean',
    ];

    public function operatingHours(): HasMany
    {
        return $this->hasMany(OperatingHour::class)->orderBy('weekday');
    }

    /**
     * Operating hours of the weekday of $at (default: now, 0 = Sunday), or null when
     * that weekday was never configured.
     */
    public function hoursOn(?\DateTimeInterface $at = null): ?OperatingHour
    {
        $weekday = ($at ? Carbon::instance($at) : now())->dayOfWeek;

        return $this->operatingHours->firstWhere('weekday', $weekday);
    }

    /**
     * Whether the store is open at $at (default: now), from the operating hours only.
     * A closed day or a day without hours counts as closed. Hours that cross midnight
     * (e.g. 18:00–02:00) keep the store open until the closing time of the next day.
     */
    public function isOpenAt(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? Carbon::instance($at) : now();
        $time = $at->format('H:i');

        $today = $this->hoursOn($at);

        if ($today?->isOpenDay()) {
            $opens = $today->opensAt();
            $closes = $today->closesAt();

            $insideToday = $closes > $opens
                ? $time >= $opens && $time < $closes
                : $time >= $opens;

            if ($insideToday) {
                return true;
            }
        }

        $yesterday = $this->hoursOn($at->copy()->subDay());

        return $yesterday !== null
            && $yesterday->isOpenDay()
            && $yesterday->closesAt() <= $yesterday->opensAt()
            && $time < $yesterday->closesAt();
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

    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class)->orderBy('number');
    }

    public function waiterCalls(): HasMany
    {
        return $this->hasMany(WaiterCall::class);
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
