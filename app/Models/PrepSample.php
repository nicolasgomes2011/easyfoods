<?php

namespace App\Models;

use App\Enums\TimeBand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * How long one product of an order really took in the kitchen, and under which
 * conditions. Input of the learned ETA (App\Support\LearnedPrepTime).
 *
 * Append-only: rows are written by RecordPrepSamples and never updated or deleted.
 */
class PrepSample extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'restaurant_id',
        'product_id',
        'order_id',
        'quantity',
        'prep_seconds',
        'kitchen_load',
        'time_band',
        'day_of_week',
        'started_at',
        'ready_at',
    ];

    protected $casts = [
        'quantity'     => 'integer',
        'prep_seconds' => 'integer',
        'kitchen_load' => 'integer',
        'time_band'    => TimeBand::class,
        'day_of_week'  => 'integer',
        'started_at'   => 'datetime',
        'ready_at'     => 'datetime',
        'created_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Prep samples are append-only.');
        });

        static::deleting(function () {
            throw new LogicException('Prep samples are append-only.');
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
