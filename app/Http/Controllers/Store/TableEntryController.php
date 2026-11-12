<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Support\TableMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Landing of the table QR code: binds the table to this browser session and
 * opens the menu in table mode. ?kiosk=1 marks a tablet fixed on the table.
 * Unknown uuid → 404 (route model binding).
 */
class TableEntryController extends Controller
{
    public function __invoke(Request $request, DiningTable $table): RedirectResponse|Response
    {
        if ($reason = TableMode::unavailableReason($table, $table->restaurant)) {
            TableMode::leave();

            return response()->view('store.table-unavailable', [
                'table'   => $table,
                'message' => $reason,
            ]);
        }

        TableMode::enter($table, $request->boolean('kiosk'));

        return redirect()->route('store.menu');
    }
}
