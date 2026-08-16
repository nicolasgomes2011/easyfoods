<?php

namespace Database\Seeders;

use App\Enums\DeliveryType;
use App\Enums\DiningTableStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TableSessionStatus;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\TableSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds demo orders covering every stage of the order lifecycle, so the
 * counter, kitchen and driver panels all have something to show after a reset.
 *
 * Invariants respected (same as App\Actions\Orders\PlaceOrder):
 *  - item rows are frozen snapshots (product_name / variant_name / unit_price),
 *    never re-read from the product at display time;
 *  - subtotal / delivery_fee / total are consistent with the item rows;
 *  - order_status_histories walks only transitions allowed by OrderStatus,
 *    and every milestone timestamp matching a visited status is set;
 *  - order numbers are sequential zero-padded strings, so PlaceOrder's
 *    "max(CAST(number AS INTEGER)) + 1" keeps producing sane numbers.
 *
 * Idempotent: an order is skipped when [restaurant_id, number] already exists.
 */
class DemoOrderSeeder extends Seeder
{
    /**
     * Happy path used to derive the status history for a given target status.
     * Sliced up to the target; Canceled is handled as a branch off the start.
     */
    private const HAPPY_PATH = [
        OrderStatus::PendingConfirmation,
        OrderStatus::Confirmed,
        OrderStatus::InPreparation,
        OrderStatus::ReadyForPickup,
        OrderStatus::OutForDelivery,
        OrderStatus::Delivered,
        OrderStatus::Completed,
    ];

    public function run(): void
    {
        $restaurant = Restaurant::where('slug', RestaurantSeeder::SLUG)->first()
            ?? Restaurant::first();

        if (! $restaurant) {
            $this->command?->warn('DemoOrderSeeder skipped: no restaurant found. Run RestaurantSeeder first.');

            return;
        }

        $products = Product::where('restaurant_id', $restaurant->id)->with('variants')->get();

        if ($products->isEmpty()) {
            $this->command?->warn('DemoOrderSeeder skipped: catalog is empty. Run CatalogSeeder first.');

            return;
        }

        $customers = Customer::orderBy('id')->get();

        foreach ($this->orders() as $index => $definition) {
            $this->seedOrder(
                $restaurant,
                $products,
                $customers,
                str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                $definition
            );
        }
    }

    private function seedOrder(
        Restaurant $restaurant,
        $products,
        $customers,
        string $number,
        array $definition
    ): void {
        $exists = Order::where('restaurant_id', $restaurant->id)
            ->where('number', $number)
            ->exists();

        if ($exists) {
            return;
        }

        DB::transaction(function () use ($restaurant, $products, $customers, $number, $definition) {
            /** @var OrderStatus $status */
            $status = $definition['status'];
            /** @var DeliveryType $deliveryType */
            $deliveryType = $definition['delivery_type'];

            // Resolve the items first — the order totals derive from them.
            $lines = [];
            $subtotal = 0.0;

            foreach ($definition['items'] as [$productName, $quantity]) {
                $product = $products->firstWhere('name', $productName) ?? $products->first();
                $variant = $product->variants->first();
                $unitPrice = (float) ($variant->price ?? $product->price);
                $lineSubtotal = $unitPrice * $quantity;
                $subtotal += $lineSubtotal;

                $lines[] = [
                    'product_id'         => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name'       => $product->name,
                    'variant_name'       => $variant?->name,
                    'unit_price'         => $unitPrice,
                    'quantity'           => $quantity,
                    'subtotal'           => $lineSubtotal,
                ];
            }

            $deliveryFee = $deliveryType === DeliveryType::Delivery ? (float) $definition['delivery_fee'] : 0.0;
            $total = $subtotal + $deliveryFee;

            $customer = $customers->get($definition['customer_index'] ?? 0);
            $placedAt = now()->subMinutes($definition['placed_minutes_ago']);

            $table = null;
            $session = null;

            if ($deliveryType === DeliveryType::DineIn) {
                $table = DiningTable::where('restaurant_id', $restaurant->id)
                    ->where('number', $definition['table_number'])
                    ->first();

                if ($table) {
                    $session = TableSession::firstOrCreate(
                        [
                            'restaurant_id'   => $restaurant->id,
                            'dining_table_id' => $table->id,
                            'status'          => TableSessionStatus::Open,
                        ],
                        ['opened_at' => $placedAt]
                    );

                    // A table with an open session must not read as free.
                    $table->update(['status' => DiningTableStatus::Occupied]);
                }
            }

            $address = $deliveryType === DeliveryType::Delivery
                ? ($customer?->addresses->first())
                : null;

            $order = Order::create(array_merge([
                'number'           => $number,
                'restaurant_id'    => $restaurant->id,
                'customer_id'      => $customer?->id,
                'dining_table_id'  => $table?->id,
                'table_session_id' => $session?->id,
                'table_number'     => $table?->number,
                'status'           => $status,
                'delivery_type'    => $deliveryType,

                'delivery_address_street'       => $address?->street,
                'delivery_address_number'       => $address?->number,
                'delivery_address_complement'   => $address?->complement,
                'delivery_address_neighborhood' => $address?->neighborhood,
                'delivery_address_city'         => $address?->city,
                'delivery_address_state'        => $address?->state,
                'delivery_address_zip'          => $address?->zip,

                'customer_name'  => $customer?->name ?? 'Cliente balcão',
                'customer_phone' => $customer?->phone,
                'notes'          => $definition['notes'] ?? null,

                'subtotal'     => $subtotal,
                'delivery_fee' => $deliveryFee,
                'discount'     => 0,
                'total'        => $total,
            ], $this->milestones($status, $deliveryType, $placedAt)));

            // created_at drives the queue ordering in the counter/kitchen panels.
            $order->forceFill(['created_at' => $placedAt, 'updated_at' => $placedAt])->saveQuietly();

            foreach ($lines as $line) {
                OrderItem::create(array_merge(['order_id' => $order->id], $line));
            }

            $this->seedStatusHistory($order, $status, $deliveryType, $placedAt);
            $this->seedPayment($order, $definition, $total, $status);
        });
    }

    /**
     * The statuses this order passed through, in order.
     *
     * @return array<int, OrderStatus>
     */
    private function visitedStatuses(OrderStatus $status, DeliveryType $deliveryType): array
    {
        if ($status === OrderStatus::Canceled) {
            return [OrderStatus::PendingConfirmation, OrderStatus::Canceled];
        }

        $path = self::HAPPY_PATH;

        // Pickup and dine-in never go out for delivery, and are closed straight
        // from ready_for_pickup — a transition the enum allows.
        if ($deliveryType !== DeliveryType::Delivery) {
            $path = array_values(array_filter(
                $path,
                fn (OrderStatus $s) => ! in_array($s, [OrderStatus::OutForDelivery, OrderStatus::Delivered], true)
            ));
        }

        $index = array_search($status, $path, true);

        return $index === false ? [OrderStatus::PendingConfirmation, $status] : array_slice($path, 0, $index + 1);
    }

    /**
     * Milestone timestamps for every visited status, spread over the elapsed time.
     *
     * @return array<string, \Illuminate\Support\Carbon>
     */
    private function milestones(OrderStatus $status, DeliveryType $deliveryType, $placedAt): array
    {
        $columns = [
            OrderStatus::Confirmed->value      => 'confirmed_at',
            OrderStatus::ReadyForPickup->value => 'ready_at',
            OrderStatus::Delivered->value      => 'delivered_at',
            OrderStatus::Completed->value      => 'completed_at',
            OrderStatus::Canceled->value       => 'canceled_at',
        ];

        $milestones = [];

        foreach ($this->visitedStatuses($status, $deliveryType) as $step => $visited) {
            if (isset($columns[$visited->value])) {
                $milestones[$columns[$visited->value]] = $placedAt->copy()->addMinutes($step * 6);
            }
        }

        return $milestones;
    }

    private function seedStatusHistory(Order $order, OrderStatus $status, DeliveryType $deliveryType, $placedAt): void
    {
        $visited = $this->visitedStatuses($status, $deliveryType);
        $previous = null;

        foreach ($visited as $step => $current) {
            OrderStatusHistory::create([
                'order_id'    => $order->id,
                'from_status' => $previous,
                'to_status'   => $current,
                'changed_at'  => $placedAt->copy()->addMinutes($step * 6),
                'notes'       => $current === OrderStatus::Canceled ? 'Cancelado pelo cliente (dado de demonstração).' : null,
            ]);

            $previous = $current;
        }
    }

    private function seedPayment(Order $order, array $definition, float $total, OrderStatus $status): void
    {
        /** @var PaymentMethod $method */
        $method = $definition['payment_method'];

        // Payment state is independent from the order status: cash is only settled
        // on handover, online methods are prepaid, an order still awaiting the
        // restaurant has nothing settled yet, and a canceled order never gets paid.
        $paymentStatus = match (true) {
            $status === OrderStatus::Canceled            => PaymentStatus::Canceled,
            $status === OrderStatus::PendingConfirmation => PaymentStatus::Pending,
            $method === PaymentMethod::Cash
                && $status !== OrderStatus::Completed
                && $status !== OrderStatus::Delivered    => PaymentStatus::Pending,
            default                                      => PaymentStatus::Paid,
        };

        $tendered = $method === PaymentMethod::Cash ? ceil($total / 10) * 10 : null;

        Payment::create([
            'order_id'        => $order->id,
            'method'          => $method,
            'status'          => $paymentStatus,
            'amount'          => $total,
            'amount_tendered' => $tendered,
            'change_due'      => $tendered ? $tendered - $total : null,
            'paid_at'         => $paymentStatus === PaymentStatus::Paid ? $order->updated_at : null,
        ]);
    }

    /**
     * One order per interesting lifecycle stage. Array order defines the order
     * number (00001, 00002, ...), and thus where PlaceOrder resumes counting.
     *
     * @return array<int, array>
     */
    private function orders(): array
    {
        return [
            [
                'status'             => OrderStatus::Completed,
                'delivery_type'      => DeliveryType::Delivery,
                'payment_method'     => PaymentMethod::Pix,
                'customer_index'     => 0,
                'delivery_fee'       => 5.00,
                'placed_minutes_ago' => 180,
                'items'              => [['X-Burger clássico', 1], ['Batata frita', 1], ['Refrigerante', 2]],
            ],
            [
                'status'             => OrderStatus::Canceled,
                'delivery_type'      => DeliveryType::Delivery,
                'payment_method'     => PaymentMethod::CreditCard,
                'customer_index'     => 1,
                'delivery_fee'       => 8.00,
                'placed_minutes_ago' => 150,
                'items'              => [['Pizza Calabresa', 1]],
                'notes'              => 'Cliente desistiu antes da confirmação.',
            ],
            [
                'status'             => OrderStatus::Delivered,
                'delivery_type'      => DeliveryType::Delivery,
                'payment_method'     => PaymentMethod::Cash,
                'customer_index'     => 2,
                'delivery_fee'       => 10.00,
                'placed_minutes_ago' => 90,
                'items'              => [['X-Bacon', 2], ['Cerveja artesanal', 2]],
            ],
            [
                'status'             => OrderStatus::OutForDelivery,
                'delivery_type'      => DeliveryType::Delivery,
                'payment_method'     => PaymentMethod::Pix,
                'customer_index'     => 0,
                'delivery_fee'       => 5.00,
                'placed_minutes_ago' => 40,
                'items'              => [['Pizza Margherita', 1], ['Suco natural', 1]],
                'notes'              => 'Interfone quebrado, ligar ao chegar.',
            ],
            [
                'status'             => OrderStatus::ReadyForPickup,
                'delivery_type'      => DeliveryType::Pickup,
                'payment_method'     => PaymentMethod::DebitCard,
                'customer_index'     => 1,
                'delivery_fee'       => 0,
                'placed_minutes_ago' => 25,
                'items'              => [['Veggie Burger', 1], ['Onion rings', 1]],
            ],
            [
                'status'             => OrderStatus::InPreparation,
                'delivery_type'      => DeliveryType::DineIn,
                'payment_method'     => PaymentMethod::Cash,
                'customer_index'     => 2,
                'delivery_fee'       => 0,
                'table_number'       => '7',
                'placed_minutes_ago' => 15,
                'items'              => [['Pizza Quatro Queijos', 1], ['Refrigerante', 3], ['Petit gateau', 2]],
                'notes'              => 'Mesa com aniversário — levar vela.',
            ],
            [
                'status'             => OrderStatus::Confirmed,
                'delivery_type'      => DeliveryType::Delivery,
                'payment_method'     => PaymentMethod::CreditCard,
                'customer_index'     => 0,
                'delivery_fee'       => 5.00,
                'placed_minutes_ago' => 8,
                'items'              => [['X-Burger clássico', 2], ['Batata frita', 2]],
            ],
            [
                'status'             => OrderStatus::PendingConfirmation,
                'delivery_type'      => DeliveryType::Delivery,
                'payment_method'     => PaymentMethod::Pix,
                'customer_index'     => 1,
                'delivery_fee'       => 8.00,
                'placed_minutes_ago' => 2,
                'items'              => [['Cheesecake de frutas vermelhas', 2], ['Água mineral 500ml', 2]],
            ],
        ];
    }
}
