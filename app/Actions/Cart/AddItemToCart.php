<?php

namespace App\Actions\Cart;

use App\Enums\DeliveryType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Restaurant;

class AddItemToCart
{
    /**
     * Customer-facing reason the selection can't be added, or null when it's valid.
     *
     * @param array<int,mixed> $selectedAddons groupId => optionId (single) | array of optionIds (multi)
     */
    public function selectionError(Product $product, ?int $variantId, array $selectedAddons): ?string
    {
        // Variant required when the product offers (available) variants.
        if ($product->variants->isNotEmpty() && ! $variantId) {
            return 'Escolha uma opção.';
        }

        // Validate addon group constraints (required / min / max).
        foreach ($product->addonGroups as $group) {
            $value = $selectedAddons[$group->id] ?? null;
            $count = is_array($value) ? count(array_filter($value)) : ($value ? 1 : 0);

            $min = $group->required ? max($group->min_choices, 1) : $group->min_choices;
            if ($count < $min) {
                return "Selecione ao menos {$min} em \"{$group->name}\".";
            }
            if ($group->max_choices && $count > $group->max_choices) {
                return "Máximo de {$group->max_choices} em \"{$group->name}\".";
            }
        }

        return null;
    }

    /** @param int[] $addonOptionIds */
    public function handle(
        Restaurant $restaurant,
        string $sessionId,
        int $productId,
        ?int $variantId,
        array $addonOptionIds,
        int $quantity,
        ?string $notes,
    ): CartItem {
        $cart = Cart::firstOrCreate(
            ['session_id' => $sessionId, 'restaurant_id' => $restaurant->id],
            ['delivery_type' => DeliveryType::Delivery->value]
        );

        // Merge only into a line with the exact same variant AND addon set.
        $existing = CartItem::with('addons')
            ->where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->get()
            ->first(function (CartItem $item) use ($addonOptionIds) {
                $itemIds = $item->addons->pluck('addon_option_id')->map('intval')->sort()->values()->all();
                return $itemIds === collect($addonOptionIds)->sort()->values()->all();
            });

        if ($existing) {
            $existing->increment('quantity', $quantity);

            return $existing;
        }

        $item = CartItem::create([
            'cart_id'            => $cart->id,
            'product_id'         => $productId,
            'product_variant_id' => $variantId,
            'quantity'           => $quantity,
            'notes'              => $notes,
        ]);

        foreach ($addonOptionIds as $optionId) {
            $item->addons()->create([
                'addon_option_id' => $optionId,
                'quantity'        => 1,
            ]);
        }

        return $item;
    }
}
