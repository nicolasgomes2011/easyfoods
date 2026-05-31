<?php

namespace App\Models;

use App\Enums\DiningTableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DiningTable extends Model
{
    protected $fillable = [
        'restaurant_id',
        'uuid',
        'number',
        'capacity',
        'status',
        'notes',
    ];

    protected $casts = [
        'status'   => DiningTableStatus::class,
        'capacity' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $table) {
            $table->uuid ??= (string) Str::uuid();
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function waiterCalls(): HasMany
    {
        return $this->hasMany(WaiterCall::class);
    }

    public function isFree(): bool
    {
        return $this->status === DiningTableStatus::Free;
    }
}
