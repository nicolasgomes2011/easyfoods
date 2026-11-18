<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The migration that adds preparation_started_at / out_for_delivery_at fills
 * them for existing orders from the append-only status history.
 */
class OrderMilestonesBackfillTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_09_000000_add_preparation_and_departure_milestones_to_orders_table.php';

    private function history(Order $order, OrderStatus $from, OrderStatus $to, string $at): void
    {
        OrderStatusHistory::create([
            'order_id'    => $order->id,
            'from_status' => $from,
            'to_status'   => $to,
            'changed_at'  => $at,
        ]);
    }

    public function test_backfill_copies_the_first_matching_transition_time(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // MySQL commits implicitly on DDL, which would leak this test's rows past RefreshDatabase.
            $this->markTestSkipped('Re-running a migration inside the test transaction is only safe on SQLite.');
        }

        $migration = require database_path(self::MIGRATION);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('orders', 'preparation_started_at'));

        $delivered = Order::factory()->create(['status' => OrderStatus::Delivered]);
        $this->history($delivered, OrderStatus::Confirmed, OrderStatus::InPreparation, '2026-11-01 12:10:00');
        $this->history($delivered, OrderStatus::ReadyForPickup, OrderStatus::OutForDelivery, '2026-11-01 12:30:00');

        $pending = Order::factory()->create(['status' => OrderStatus::PendingConfirmation]);

        $migration->up();

        $row = DB::table('orders')->where('id', $delivered->id)->first();
        $this->assertSame('2026-11-01 12:10:00', $row->preparation_started_at);
        $this->assertSame('2026-11-01 12:30:00', $row->out_for_delivery_at);

        $untouched = DB::table('orders')->where('id', $pending->id)->first();
        $this->assertNull($untouched->preparation_started_at);
        $this->assertNull($untouched->out_for_delivery_at);
    }
}
