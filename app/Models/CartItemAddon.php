<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItemAddon extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_item_id',
        'addon_option_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function cartItem(): BelongsTo
    {
        return $this->belongsTo(CartItem::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(AddonOption::class, 'addon_option_id');
    }
}
