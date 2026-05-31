<?php

namespace App\Actions\Orders;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Restaurant;
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
                return $item->unitPrice() * $item->quantity;
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
                $unitPrice = $cartItem->unitPrice();
                $itemSubtotal = $unitPrice * $cartItem->quantity;

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

            // Clear cart
            $cart->items()->each(fn ($item) => $item->addons()->delete() && $item->delete());
            $cart->delete();

            return $order;
        });
    }
}
