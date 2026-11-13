<?php

namespace App\Models;

use App\Enums\DeliveryType;
use App\Enums\OrderItemChangeType;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $order) {
            $order->token ??= (string) Str::uuid();
        });
    }

    protected $fillable = [
        'number',
        'sequence',
        'token',
        'restaurant_id',
        'customer_id',
        'dining_table_id',
        'table_session_id',
        'table_number',
        'driver_id',
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
        'preparation_started_at',
        'ready_at',
        'out_for_delivery_at',
        'delivered_at',
        'completed_at',
        'canceled_at',
        'estimated_ready_at',
        'eta_adjustments',
    ];

    protected $casts = [
        'sequence'      => 'integer',
        'status'        => OrderStatus::class,
        'delivery_type' => DeliveryType::class,
        'subtotal'      => 'decimal:2',
        'delivery_fee'  => 'decimal:2',
        'discount'      => 'decimal:2',
        'total'         => 'decimal:2',
        'confirmed_at'  => 'datetime',
        'preparation_started_at' => 'datetime',
        'ready_at'      => 'datetime',
        'out_for_delivery_at'    => 'datetime',
        'delivered_at'  => 'datetime',
        'completed_at'  => 'datetime',
        'canceled_at'   => 'datetime',
        'estimated_ready_at' => 'datetime',
        'eta_adjustments'    => 'integer',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /** The driver who accepted the delivery on the driver panel (null: still available). */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function deliveryDeclines(): HasMany
    {
        return $this->hasMany(DeliveryDecline::class);
    }

    /**
     * Delivery orders of the restaurant that no driver owns yet and that a driver can
     * take: ready to leave, or already out for delivery without a driver (sent out
     * before deliveries had an owner, so someone can still finish them).
     */
    public function scopeAvailableForDelivery($query, ?int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId)
            ->where('delivery_type', DeliveryType::Delivery->value)
            ->whereNull('driver_id')
            ->whereIn('status', [OrderStatus::ReadyForPickup->value, OrderStatus::OutForDelivery->value]);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('changed_at');
    }

    /** Items added to / removed from the order after it was placed (append-only log). */
    public function itemChanges(): HasMany
    {
        return $this->hasMany(OrderItemChange::class)->orderBy('changed_at')->orderBy('id');
    }

    /**
     * Items removed after the kitchen got the order (from its confirmation on):
     * the kitchen card keeps them struck through so nobody keeps preparing them.
     * Removals before the confirmation were never seen by the kitchen.
     *
     * @return Collection<int, OrderItemChange>
     */
    public function removalsSeenByKitchen(): Collection
    {
        return $this->itemChanges
            ->filter(fn (OrderItemChange $change) => $change->type === OrderItemChangeType::Removed
                && ($this->confirmed_at === null || $change->changed_at->gte($this->confirmed_at)))
            ->values();
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

    /** `tel:` link to call the customer (digits only, keeps a leading +), or null without a phone. */
    public function customerPhoneUri(): ?string
    {
        $phone = trim((string) $this->customer_phone);
        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            return null;
        }

        return 'tel:'.(str_starts_with($phone, '+') ? '+' : '').$digits;
    }

    /**
     * Google Maps link to the delivery address (opens the maps app on the phone),
     * or null without a street. The complement (apartment, block) is left out: it
     * does not help to find the place on the map.
     */
    public function deliveryMapUrl(): ?string
    {
        if (blank($this->delivery_address_street)) {
            return null;
        }

        $cityAndState = collect([$this->delivery_address_city, $this->delivery_address_state])
            ->filter(fn ($part) => filled($part))
            ->implode(' - ');

        $address = collect([
            $this->delivery_address_street,
            $this->delivery_address_number,
            $this->delivery_address_neighborhood,
            $cityAndState,
            $this->delivery_address_zip,
        ])->filter(fn ($part) => filled($part))->implode(', ');

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address);
    }

    /**
     * Staff may still add or remove items: dine-in orders only, while the order
     * is not ready yet (pending confirmation, confirmed or in preparation).
     * Delivery and pickup orders are fixed once placed.
     */
    public function itemsAreEditable(): bool
    {
        return $this->delivery_type === DeliveryType::DineIn
            && in_array($this->status, [
                OrderStatus::PendingConfirmation,
                OrderStatus::Confirmed,
                OrderStatus::InPreparation,
            ], true);
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
