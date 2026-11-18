<?php

namespace App\Actions\Dining;

use App\Enums\DiningTableStatus;
use App\Models\DiningTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff changes a table's status by hand. A table with an open tab only leaves
 * through "Fechar mesa", so free/reserved are refused while a session is open.
 *
 * The check and the write run under a row lock on the table: PlaceOrder writes
 * the same row before it opens a session, so a dine-in order landing at the
 * same moment either waits for this change or is seen by the check.
 */
class SetDiningTableStatus
{
    /**
     * @throws ValidationException
     */
    public function execute(int $restaurantId, int $tableId, DiningTableStatus $status): void
    {
        DB::transaction(function () use ($restaurantId, $tableId, $status) {
            $table = DiningTable::where('restaurant_id', $restaurantId)
                ->lockForUpdate()
                ->findOrFail($tableId);

            if ($status !== DiningTableStatus::Occupied && $table->openSession()->exists()) {
                throw ValidationException::withMessages([
                    'status' => "A mesa {$table->number} está com a conta aberta. Feche a mesa primeiro.",
                ]);
            }

            $table->update(['status' => $status]);
        });
    }
}
