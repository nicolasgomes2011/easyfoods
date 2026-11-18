<?php

namespace App\Actions\Waste;

use App\Exceptions\WasteNotRecordable;
use App\Models\User;
use App\Models\WasteEntry;
use Illuminate\Support\Facades\DB;

/**
 * Corrects a waste entry without changing it: a new entry pointing at the
 * original (adjusts_id) records the difference between the corrected quantity
 * and the current one (original + earlier corrections), with the original
 * snapshot (ingredient, unit, cost, reason). Correcting to 0 cancels it.
 */
class CorrectWasteEntry
{
    /**
     * @throws WasteNotRecordable
     */
    public function execute(WasteEntry $entry, float|string $correctQuantity, User $actor): WasteEntry
    {
        if ($entry->isAdjustment()) {
            throw new WasteNotRecordable('Corrija o lançamento original, não a correção.');
        }

        $correct = round((float) $correctQuantity, 3);

        if ($correct < 0 || $correct > RecordWaste::MAX_QUANTITY) {
            throw new WasteNotRecordable('Informe a quantidade correta (0 se o lançamento foi feito por engano).');
        }

        return DB::transaction(function () use ($entry, $correct, $actor) {
            // Locked so two corrections at once do not both start from the same total.
            $original = WasteEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $current = $original->effectiveQuantity();
            $difference = round($correct - $current, 3);

            if ($difference == 0.0) {
                throw new WasteNotRecordable('A quantidade já é essa. Não há o que corrigir.');
            }

            return WasteEntry::create([
                'restaurant_id'   => $original->restaurant_id,
                'ingredient_id'   => $original->ingredient_id,
                'adjusts_id'      => $original->id,
                'ingredient_name' => $original->ingredient_name,
                'unit'            => $original->unit,
                'unit_cost'       => $original->unit_cost,
                'quantity'        => $difference,
                'reason'          => $original->reason,
                'note'            => 'Correção: de '.$original->formattedQuantity($current).' para '.$original->formattedQuantity($correct),
                'recorded_by'     => $actor->id,
            ]);
        });
    }
}
