<?php

namespace App\Models;

use App\Enums\ProductAvailabilityStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'price',
        'availability_status',
        'sort_order',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'availability_status' => ProductAvailabilityStatus::class,
        'sort_order'          => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isAvailable(): bool
    {
        return $this->availability_status->isOrderable();
    }

    public function scopeAvailable($query)
    {
        return $query->where('availability_status', ProductAvailabilityStatus::Available->value);
    }
}
