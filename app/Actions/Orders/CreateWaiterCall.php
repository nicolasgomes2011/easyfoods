<?php

namespace App\Actions\Orders;

use App\Enums\WaiterCallStatus;
use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\WaiterCall;

class CreateWaiterCall
{
    public function handle(Restaurant $restaurant, DiningTable $table, string $sessionId): WaiterCall
    {
        // Deduplicate: if there's already a pending call from this session+table, return it
        $existing = WaiterCall::where('restaurant_id', $restaurant->id)
            ->where('dining_table_id', $table->id)
            ->where('session_id', $sessionId)
            ->where('status', WaiterCallStatus::Pending->value)
            ->first();

        if ($existing) {
            return $existing;
        }

        return WaiterCall::create([
            'restaurant_id'   => $restaurant->id,
            'dining_table_id' => $table->id,
            'session_id'      => $sessionId,
            'status'          => WaiterCallStatus::Pending,
            'called_at'       => now(),
        ]);
    }
}
