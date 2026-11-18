<?php

namespace App\Support;

use App\Enums\DiningTableStatus;
use App\Models\DiningTable;
use App\Models\Restaurant;

/**
 * "Table mode" of the storefront: the table scanned from its QR code
 * (/mesa/{uuid}) is kept in the browser session, so the menu and checkout
 * place a dine-in order for that table. Kiosk = tablet fixed on the table:
 * the customer can't leave table mode.
 */
final class TableMode
{
    private const KEY = 'table_mode';

    public static function enter(DiningTable $table, bool $kiosk = false): void
    {
        session()->put(self::KEY, [
            'table_id' => $table->id,
            'kiosk'    => $kiosk,
        ]);
    }

    public static function leave(): void
    {
        session()->forget(self::KEY);
    }

    /** The session's table, only if it belongs to this restaurant. */
    public static function table(?Restaurant $restaurant): ?DiningTable
    {
        $tableId = session(self::KEY.'.table_id');

        if (! $tableId || ! $restaurant) {
            return null;
        }

        return DiningTable::where('restaurant_id', $restaurant->id)->find($tableId);
    }

    public static function isKiosk(): bool
    {
        return (bool) session(self::KEY.'.kiosk', false);
    }

    /** Customer-facing reason the table can't take an order now, or null. */
    public static function unavailableReason(DiningTable $table, ?Restaurant $restaurant): ?string
    {
        if (! $restaurant || ! $restaurant->accepts_dine_in) {
            return 'Este restaurante não está aceitando pedidos na mesa no momento.';
        }

        if ($table->status === DiningTableStatus::Reserved) {
            return "A mesa {$table->number} está reservada no momento. Chame um atendente para fazer o pedido.";
        }

        return null;
    }
}
