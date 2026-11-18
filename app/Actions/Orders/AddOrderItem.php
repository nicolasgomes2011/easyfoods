<?php

namespace App\Actions\Orders;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Orders\Concerns\EditsOpenOrder;
use App\Enums\OrderItemChangeType;
use App\Exceptions\OrderNotEditable;
use App\Models\AddonOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemChange;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staff adds an item to an open dine-in order (e.g. one more drink at the table).
 *
 * The line is frozen like at checkout: product/variant/addon names and current
 * prices are copied into the order (same selection rules as the cart), the
 * order totals are recomputed on the server and the change is logged in
 * order_item_changes. The order status does not change.
 */
class AddOrderItem
{
    use EditsOpenOrder;

    public const MAX_QUANTITY = 99;

    /**
     * @param array<int,mixed> $selectedAddons groupId => optionId (single) | array of optionIds (multi)
     *
     * @throws OrderNotEditable
     */
    public function execute(
        Order $order,
        Product $product,
        ?int $variantId,
        array $selectedAddons,
        int $quantity,
        ?string $notes,
        User $actor,
    ): OrderItem {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new OrderNotEditable('Quantidade inválida.');
        }

        return DB::transaction(function () use ($order, $product, $variantId, $selectedAddons, $quantity, $notes, $actor) {
            $order = $this->lockEditableOrder($order);

            // Never let an order take a product of another restaurant.
            if ((int) $product->restaurant_id !== (int) $order->restaurant_id) {
                throw new OrderNotEditable('Produto não encontrado.');
            }

            if ($product->isArchived() || ! $product->isAvailable()) {
                throw new OrderNotEditable("\"{$product->name}\" não está disponível no cardápio.");
            }

            $cartRules = app(AddItemToCart::class);
            if ($error = $cartRules->selectionError($product, $variantId, $selectedAddons)) {
                throw new OrderNotEditable($error);
            }

            $variant = $variantId
                ? ProductVariant::where('product_id', $product->id)->findOrFail($variantId)
                : null;

            $optionIds = collect($selectedAddons)
                ->flatten()
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $options = AddonOption::with('group')->whereIn('id', $optionIds)->get();

            $unitPrice = (float) ($variant?->price ?? $product->price);
            $addonsPerUnit = (float) $options->sum(fn (AddonOption $option) => (float) $option->price);
            $subtotal = round(($unitPrice + $addonsPerUnit) * $quantity, 2);

            $item = OrderItem::create([
                'order_id'           => $order->id,
                'product_id'         => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name'       => $product->name,
                'variant_name'       => $variant?->name,
                'unit_price'         => $unitPrice,
                'quantity'           => $quantity,
                'subtotal'           => $subtotal,
                'notes'              => filled($notes) ? trim($notes) : null,
                'added_at'           => now(),
            ]);

            // Same addon snapshot as PlaceOrder: one of each chosen option per unit.
            foreach ($options as $option) {
                $item->addons()->create([
                    'addon_option_id'   => $option->id,
                    'addon_group_name'  => $option->group->name ?? '',
                    'addon_option_name' => $option->name,
                    'unit_price'        => $option->price,
                    'quantity'          => 1,
                    'subtotal'          => $option->price,
                ]);
            }

            OrderItemChange::create([
                'restaurant_id' => $order->restaurant_id,
                'order_id'      => $order->id,
                'order_item_id' => $item->id,
                'type'          => OrderItemChangeType::Added,
                'product_name'  => $item->product_name,
                'variant_name'  => $item->variant_name,
                'quantity'      => $item->quantity,
                'subtotal'      => $item->subtotal,
                'reason'        => null,
                'changed_by'    => $actor->id,
                'changed_at'    => now(),
            ]);

            $this->syncTotals($order);

            return $item;
        });
    }
}
