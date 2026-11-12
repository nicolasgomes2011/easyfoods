<?php

namespace App\Models;

use App\Enums\OrderItemChangeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An item added to or removed from an open order after it was placed.
 * Append-only: rows are written by AddOrderItem / RemoveOrderItem and never updated.
 */
class OrderItemChange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'restaurant_id',
        'order_id',
        'order_item_id',
        'type',
        'product_name',
        'variant_name',
        'quantity',
        'subtotal',
        'reason',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'type'       => OrderItemChangeType::class,
        'quantity'   => 'integer',
        'subtotal'   => 'decimal:2',
        'changed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function displayName(): string
    {
        return $this->variant_name
            ? "{$this->product_name} ({$this->variant_name})"
            : $this->product_name;
    }
}
