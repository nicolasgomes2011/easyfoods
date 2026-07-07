<?php

namespace App\Models;

use App\Enums\TableSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'dining_table_id',
        'status',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'status'    => TableSessionStatus::class,
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isOpen(): bool
    {
        return $this->status === TableSessionStatus::Open;
    }

    public function scopeOpen($query)
    {
        return $query->where('status', TableSessionStatus::Open->value);
    }

    /** Blocks closing the tab while food/payment for it is still in flight. */
    public function hasActiveOrders(): bool
    {
        return $this->orders()->active()->exists();
    }
}
