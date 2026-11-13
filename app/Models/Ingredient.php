<?php

namespace App\Models;

use App\Enums\IngredientCategory;
use App\Enums\IngredientUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An ingredient (insumo) the restaurant buys, with its cost per unit. */
class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'name',
        'unit',
        'category',
        'cost_per_unit',
        'archived_at',
    ];

    protected $casts = [
        'unit'          => IngredientUnit::class,
        'category'      => IngredientCategory::class,
        'cost_per_unit' => 'decimal:4',
        'archived_at'   => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** "45,90", or up to 4 decimals for costs below a cent ("0,045"). */
    public function formattedCost(): string
    {
        $formatted = number_format((float) $this->cost_per_unit, 4, ',', '.');

        // Drop trailing zeros, but keep at least 2 decimals.
        return preg_replace('/(,\d{2}\d*?)0+$/', '$1', $formatted);
    }
}
