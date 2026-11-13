<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'unit_price',
        'quantity',
        'subtotal',
        'notes',
        'added_at',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal'   => 'decimal:2',
        'quantity'   => 'integer',
        // Set when staff added the item to the open order after it was placed.
        'added_at'   => 'datetime',
        // Kitchen checklist: set when the kitchen ticked the item off (never mass-assigned).
        'prepared_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(OrderItemAddon::class);
    }

    /** Added by staff after the order was placed (the kitchen highlights it). */
    public function wasAddedLater(): bool
    {
        return $this->added_at !== null;
    }

    /** Ticked off on the kitchen checklist. */
    public function isPrepared(): bool
    {
        return $this->prepared_at !== null;
    }

    public function displayName(): string
    {
        return $this->variant_name
            ? "{$this->product_name} ({$this->variant_name})"
            : $this->product_name;
    }
}
