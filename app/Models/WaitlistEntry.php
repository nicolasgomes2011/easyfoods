<?php

namespace App\Models;

use App\Enums\WaitlistStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'party_name',
        'party_size',
        'phone',
        'status',
        'dining_table_id',
        'notes',
        'seated_at',
        'removed_at',
    ];

    protected $casts = [
        'status'     => WaitlistStatus::class,
        'party_size' => 'integer',
        'seated_at'  => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', WaitlistStatus::Waiting->value);
    }

    public function isWaiting(): bool
    {
        return $this->status === WaitlistStatus::Waiting;
    }
}
