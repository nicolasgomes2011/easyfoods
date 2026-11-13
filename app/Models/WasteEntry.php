<?php

namespace App\Models;

use App\Enums\IngredientUnit;
use App\Enums\WasteReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * An ingredient thrown away (or a correction of one), with a snapshot of the
 * ingredient name, unit and cost at that moment.
 *
 * Append-only: rows are written by RecordWaste / CorrectWasteEntry and never
 * updated or deleted. A correction is a new row (adjusts_id) with the difference.
 */
class WasteEntry extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'restaurant_id',
        'ingredient_id',
        'adjusts_id',
        'ingredient_name',
        'unit',
        'unit_cost',
        'quantity',
        'reason',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'unit'       => IngredientUnit::class,
        'reason'     => WasteReason::class,
        'unit_cost'  => 'decimal:4',
        'quantity'   => 'decimal:3',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Waste entries are append-only: record a correction instead.');
        });

        static::deleting(function () {
            throw new LogicException('Waste entries are append-only: record a correction instead.');
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** The entry this one corrects (only on a correction). */
    public function adjusts(): BelongsTo
    {
        return $this->belongsTo(self::class, 'adjusts_id');
    }

    /** Corrections of this entry. */
    public function adjustments(): HasMany
    {
        return $this->hasMany(self::class, 'adjusts_id');
    }

    public function isAdjustment(): bool
    {
        return $this->adjusts_id !== null;
    }

    /**
     * Quantity after its corrections. Uses `withSum('adjustments', 'quantity')`
     * when loaded (lists), or one query otherwise.
     */
    public function effectiveQuantity(): float
    {
        $adjusted = array_key_exists('adjustments_sum_quantity', $this->attributes)
            ? $this->attributes['adjustments_sum_quantity']
            : $this->adjustments()->sum('quantity');

        return round((float) $this->quantity + (float) $adjusted, 3);
    }

    /** quantity × snapshot unit cost (negative on a correction that lowers the original). */
    public function totalCost(): float
    {
        return round((float) $this->quantity * (float) $this->unit_cost, 2);
    }

    /** "1,5", "2", "0,25"; with a minus sign when negative. */
    public static function formatQuantity(float $quantity): string
    {
        $formatted = rtrim(rtrim(number_format(abs($quantity), 3, ',', '.'), '0'), ',');

        return ($quantity < 0 ? '-' : '').$formatted;
    }

    /** "R$ 12,75"; with a minus sign when negative. */
    public static function formatMoney(float $value): string
    {
        return ($value < 0 ? '-' : '').'R$ '.number_format(abs($value), 2, ',', '.');
    }

    /** "1,5 kg" */
    public function formattedQuantity(?float $quantity = null): string
    {
        return self::formatQuantity($quantity ?? (float) $this->quantity).' '.$this->unit->symbol();
    }
}
