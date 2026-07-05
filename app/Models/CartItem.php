<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
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
        return $this->hasMany(CartItemAddon::class);
    }

    public function unitPrice(): float
    {
        return (float) ($this->variant?->price ?? $this->product->price);
    }

    /**
     * Sum of the selected addon prices for a single unit of this item.
     */
    public function addonsTotal(): float
    {
        return (float) $this->addons->sum(
            fn (CartItemAddon $addon) => (float) ($addon->option->price ?? 0) * $addon->quantity
        );
    }

    /**
     * Full price for this cart line: (base + addons) * quantity.
     */
    public function lineTotal(): float
    {
        return ($this->unitPrice() + $this->addonsTotal()) * $this->quantity;
    }
}
