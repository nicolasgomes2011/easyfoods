<?php

namespace App\Models;

use App\Enums\WaiterCallStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaiterCall extends Model
{
    protected $fillable = [
        'restaurant_id',
        'dining_table_id',
        'status',
        'called_at',
        'acknowledged_at',
    ];

    protected $casts = [
        'status'          => WaiterCallStatus::class,
        'called_at'       => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    public function isPending(): bool
    {
        return $this->status === WaiterCallStatus::Pending;
    }

    public function scopeForRestaurant($query, int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    public function scopePending($query)
    {
        return $query->where('status', WaiterCallStatus::Pending->value);
    }
}
