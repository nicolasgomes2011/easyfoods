<?php

namespace App\Models;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'restaurant_id',
        'customer_id',
        'status',
        'delivery_type',
        'delivery_address_street',
        'delivery_address_number',
        'delivery_address_complement',
        'delivery_address_neighborhood',
        'delivery_address_city',
        'delivery_address_state',
        'delivery_address_zip',
        'subtotal',
        'delivery_fee',
        'discount',
        'total',
        'customer_name',
        'customer_phone',
        'notes',
        'confirmed_at',
        'ready_at',
        'delivered_at',
        'completed_at',
        'canceled_at',
    ];

    protected $casts = [
        'status'        => OrderStatus::class,
        'delivery_type' => DeliveryType::class,
        'subtotal'      => 'decimal:2',
        'delivery_fee'  => 'decimal:2',
        'discount'      => 'decimal:2',
        'total'         => 'decimal:2',
        'confirmed_at'  => 'datetime',
        'ready_at'      => 'datetime',
        'delivered_at'  => 'datetime',
        'completed_at'  => 'datetime',
        'canceled_at'   => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('changed_at');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isCancelable(): bool
    {
        return $this->status->canTransitionTo(OrderStatus::Canceled);
    }

    public function isInFinalState(): bool
    {
        return $this->status->isFinal();
    }

    public function isDelivery(): bool
    {
        return $this->delivery_type === DeliveryType::Delivery;
    }

    public function scopeByStatus($query, OrderStatus $status)
    {
        return $query->where('status', $status->value);
    }

    public function scopeActive($query)
    {
        $activeStatuses = array_map(
            fn (OrderStatus $s) => $s->value,
            array_filter(OrderStatus::cases(), fn ($s) => $s->isActive())
        );

        return $query->whereIn('status', $activeStatuses);
    }

    public function scopeForRestaurant($query, int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }
}
