<?php

namespace App\Actions\Orders;

use App\Enums\DeliveryType;
use App\Enums\DiningTableStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\Cart;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Restaurant;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;

class PlaceOrder
{
    public function handle(Cart $cart, array $data): Order
    {
        return DB::transaction(function () use ($cart, $data) {
            $restaurant = Restaurant::findOrFail($cart->restaurant_id);
            $deliveryType = DeliveryType::from($data['delivery_type']);

            // Build frozen snapshot of items
            $items = $cart->items()->with(['product', 'variant', 'addons.option'])->get();

            if ($items->isEmpty()) {
                throw new \RuntimeException('O carrinho está vazio.');
            }

            $subtotal = $items->sum(function ($item) {
                return $item->lineTotal();
            });

            $deliveryFee = $deliveryType === DeliveryType::Delivery
                ? (float) ($data['delivery_fee'] ?? 0)
                : 0.0;

            $total = $subtotal + $deliveryFee;

            // Generate sequential order number
            $lastNumber = (int) Order::where('restaurant_id', $restaurant->id)
                ->lockForUpdate()
                ->max(DB::raw('CAST(number AS INTEGER)'));

            $number = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);

            $order = Order::create([
                'number'          => $number,
                'restaurant_id'   => $restaurant->id,
                'customer_id'     => $cart->customer_id,
                'status'          => OrderStatus::PendingConfirmation,
                'delivery_type'   => $deliveryType,
                'dining_table_id' => $data['dining_table_id'] ?? null,
                'table_number'    => $data['table_number'] ?? null,

                // Delivery address snapshot (null for pickup/dine-in)
                'delivery_address_street'       => $data['street'] ?? null,
                'delivery_address_number'       => $data['address_number'] ?? null,
                'delivery_address_complement'   => $data['complement'] ?? null,
                'delivery_address_neighborhood' => $data['neighborhood'] ?? null,
                'delivery_address_city'         => $data['city'] ?? null,
                'delivery_address_state'        => $data['state'] ?? null,
                'delivery_address_zip'          => $data['zip'] ?? null,

                'customer_name'  => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'notes'          => $data['notes'] ?? null,

                'subtotal'     => $subtotal,
                'delivery_fee' => $deliveryFee,
                'discount'     => 0,
                'total'        => $total,
            ]);

            // Frozen item snapshots
            foreach ($items as $cartItem) {
                // A product can be paused or archived between add-to-cart and
                // checkout — re-check here so it never slips into a placed order.
                $product = $cartItem->product;
                if (! $product || $product->isArchived() || ! $product->isAvailable()) {
                    $name = $product?->name ?? 'Um dos itens';
                    throw new \RuntimeException("\"{$name}\" não está mais disponível no cardápio.");
                }

                $unitPrice = $cartItem->unitPrice();
                // Line subtotal includes the selected addons (folded into the line so the
                // order subtotal/total stay coherent with what the customer sees).
                $itemSubtotal = $cartItem->lineTotal();

                $orderItem = OrderItem::create([
                    'order_id'           => $order->id,
                    'product_id'         => $cartItem->product_id,
                    'product_variant_id' => $cartItem->product_variant_id,
                    'product_name'       => $cartItem->product->name,
                    'variant_name'       => $cartItem->variant?->name,
                    'unit_price'         => $unitPrice,
                    'quantity'           => $cartItem->quantity,
                    'subtotal'           => $itemSubtotal,
                    'notes'              => $cartItem->notes,
                ]);

                foreach ($cartItem->addons as $addon) {
                    $addonPrice = $addon->option->price ?? 0;
                    $orderItem->addons()->create([
                        'addon_option_id'    => $addon->addon_option_id,
                        'addon_group_name'   => $addon->option->group->name ?? '',
                        'addon_option_name'  => $addon->option->name,
                        'unit_price'         => $addonPrice,
                        'quantity'           => $addon->quantity,
                        'subtotal'           => $addonPrice * $addon->quantity,
                    ]);
                }
            }

            // Status history entry
            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'from_status' => null,
                'to_status'  => OrderStatus::PendingConfirmation->value,
                'changed_by' => null,
                'changed_at' => now(),
            ]);

            // Dine-in: the table becomes occupied and joins the tab (table session)
            // the moment the order lands — opening one if this is the first order
            // of the sitting, or reusing the one already open. Dining-room state
            // only — order status/history is not involved. The table lookup is
            // scoped to this restaurant, so a foreign table id is simply ignored.
            if ($deliveryType === DeliveryType::DineIn && $order->dining_table_id) {
                $table = DiningTable::where('restaurant_id', $restaurant->id)
                    ->whereKey($order->dining_table_id)
                    ->first();

                if ($table) {
                    $table->update(['status' => DiningTableStatus::Occupied->value]);

                    $session = TableSession::where('restaurant_id', $restaurant->id)
                        ->where('dining_table_id', $table->id)
                        ->open()
                        ->first();

                    $session ??= TableSession::create([
                        'restaurant_id'   => $restaurant->id,
                        'dining_table_id' => $table->id,
                        'status'          => TableSessionStatus::Open,
                        'opened_at'       => now(),
                    ]);

                    $order->update(['table_session_id' => $session->id]);
                }
            }

            // Clear cart
            $cart->items()->each(fn ($item) => $item->addons()->delete() && $item->delete());
            $cart->delete();

            return $order;
        });
    }
}
