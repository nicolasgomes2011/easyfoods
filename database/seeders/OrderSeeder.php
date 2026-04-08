<?php

namespace Database\Seeders;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    private int $sequence = 1;

    public function run(): void
    {
        $restaurant = Restaurant::where('slug', 'easyfoods-demo')->firstOrFail();

        $customers = Customer::factory(5)->create();
        $products  = Product::where('restaurant_id', $restaurant->id)->get();

        $scenarios = [
            ['status' => OrderStatus::PendingConfirmation, 'delivery_type' => DeliveryType::Delivery],
            ['status' => OrderStatus::Confirmed,           'delivery_type' => DeliveryType::Delivery],
            ['status' => OrderStatus::InPreparation,       'delivery_type' => DeliveryType::Pickup],
            ['status' => OrderStatus::ReadyForPickup,      'delivery_type' => DeliveryType::Pickup],
            ['status' => OrderStatus::OutForDelivery,      'delivery_type' => DeliveryType::Delivery],
            ['status' => OrderStatus::Delivered,           'delivery_type' => DeliveryType::Delivery],
            ['status' => OrderStatus::Completed,           'delivery_type' => DeliveryType::Delivery],
            ['status' => OrderStatus::Canceled,            'delivery_type' => DeliveryType::Delivery],
        ];

        foreach ($scenarios as $scenario) {
            $this->createOrder($restaurant, $customers->random(), $products->random(2), $scenario);
        }
    }

    private function createOrder($restaurant, $customer, $selectedProducts, array $scenario): void
    {
        $subtotal   = 0;
        $orderItems = [];

        foreach ($selectedProducts as $product) {
            $qty     = rand(1, 3);
            $price   = (float) $product->price;
            $itemSub = round($price * $qty, 2);
            $subtotal += $itemSub;
            $orderItems[] = [
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'unit_price'   => $price,
                'quantity'     => $qty,
                'subtotal'     => $itemSub,
            ];
        }

        $deliveryFee = $scenario['delivery_type'] === DeliveryType::Delivery ? 8.00 : 0;
        $total       = $subtotal + $deliveryFee;

        $order = Order::create([
            'number'         => str_pad($this->sequence++, 5, '0', STR_PAD_LEFT),
            'restaurant_id'  => $restaurant->id,
            'customer_id'    => $customer->id,
            'status'         => $scenario['status'],
            'delivery_type'  => $scenario['delivery_type'],
            'delivery_address_street'       => 'Rua Exemplo',
            'delivery_address_number'       => '42',
            'delivery_address_neighborhood' => 'Centro',
            'delivery_address_city'         => 'São Paulo',
            'delivery_address_state'        => 'SP',
            'delivery_address_zip'          => '01310-100',
            'subtotal'       => $subtotal,
            'delivery_fee'   => $deliveryFee,
            'discount'       => 0,
            'total'          => $total,
            'customer_name'  => $customer->name,
            'customer_phone' => $customer->phone,
        ]);

        foreach ($orderItems as $item) {
            OrderItem::create(array_merge(['order_id' => $order->id], $item));
        }

        // Status history entry
        OrderStatusHistory::create([
            'order_id'   => $order->id,
            'from_status' => null,
            'to_status'  => $scenario['status']->value,
            'changed_at' => now(),
        ]);

        // Payment
        Payment::create([
            'order_id' => $order->id,
            'method'   => PaymentMethod::Pix,
            'status'   => $scenario['status'] === OrderStatus::Completed
                ? PaymentStatus::Paid
                : PaymentStatus::Pending,
            'amount'   => $total,
            'paid_at'  => $scenario['status'] === OrderStatus::Completed ? now() : null,
        ]);
    }
}
