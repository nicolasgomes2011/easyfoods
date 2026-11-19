<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'restaurant_id',
        'name',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    /**
     * Customers a restaurant can see: its own (created by its checkout), plus
     * older rows without restaurant_id that ordered there.
     */
    public function scopeOfRestaurant(Builder $query, ?int $restaurantId): Builder
    {
        return $query->where(fn ($q) => $q->where('restaurant_id', $restaurantId)
            ->orWhere(fn ($q) => $q->whereNull('restaurant_id')
                ->whereHas('orders', fn ($q) => $q->where('restaurant_id', $restaurantId))));
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function defaultAddress(): ?CustomerAddress
    {
        return $this->addresses->firstWhere('is_default', true)
            ?? $this->addresses->first();
    }
}
