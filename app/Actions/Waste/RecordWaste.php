<?php

namespace App\Actions\Waste;

use App\Enums\WasteReason;
use App\Exceptions\WasteNotRecordable;
use App\Models\Ingredient;
use App\Models\User;
use App\Models\WasteEntry;

/**
 * Staff records an ingredient thrown away: how much (in the ingredient unit)
 * and why. The ingredient name, unit and cost per unit are snapshotted, so the
 * entry keeps its value when the ingredient price changes later.
 */
class RecordWaste
{
    public const MAX_QUANTITY = 99999;

    /**
     * @throws WasteNotRecordable
     */
    public function execute(Ingredient $ingredient, float|string $quantity, WasteReason $reason, ?string $note, User $actor): WasteEntry
    {
        if ($ingredient->isArchived()) {
            throw new WasteNotRecordable('Este insumo está arquivado. Reative-o no cadastro de insumos para registrar o descarte.');
        }

        $quantity = round((float) $quantity, 3);

        if ($quantity <= 0 || $quantity > self::MAX_QUANTITY) {
            throw new WasteNotRecordable('Informe uma quantidade maior que zero.');
        }

        $note = trim((string) $note);

        return WasteEntry::create([
            'restaurant_id'   => $ingredient->restaurant_id,
            'ingredient_id'   => $ingredient->id,
            'ingredient_name' => $ingredient->name,
            'unit'            => $ingredient->unit,
            'unit_cost'       => $ingredient->cost_per_unit,
            'quantity'        => $quantity,
            'reason'          => $reason,
            'note'            => $note === '' ? null : $note,
            'recorded_by'     => $actor->id,
        ]);
    }
}
