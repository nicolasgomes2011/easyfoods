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
     * The variant / addon ids come from the client, so they are checked against
     * the product's own orderable variants and active addon groups/options —
     * queried here, never trusted from whatever the caller eager-loaded.
     *
     * @param array<int,mixed> $selectedAddons groupId => optionId (single) | array of optionIds (multi)
     */
    public function selectionError(Product $product, ?int $variantId, array $selectedAddons): ?string
    {
        $variantIds = $product->variants()->available()->pluck('id');

        if ($variantId && ! $variantIds->contains($variantId)) {
            return 'Essa opção não está mais disponível.';
        }

        // Variant required when the product offers (available) variants.
        if ($variantIds->isNotEmpty() && ! $variantId) {
            return 'Escolha uma opção.';
        }

        $groups = $product->addonGroups()->active()->with('activeOptions')->get()->keyBy('id');

        // Every chosen option must be an active option of an active group of this product.
        foreach ($selectedAddons as $groupId => $value) {
            $optionIds = $this->optionIds($value);
            if ($optionIds === []) {
                continue;
            }

            $group = $groups->get($groupId);
            if (! $group || array_diff($optionIds, $group->activeOptions->pluck('id')->all()) !== []) {
                return 'Um dos adicionais escolhidos não está disponível.';
            }
        }

        // Validate addon group constraints (required / min / max).
        foreach ($groups as $group) {
            $count = count($this->optionIds($selectedAddons[$group->id] ?? null));

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

    /**
     * Re-validates the selection itself, so nothing reaches the cart through a
     * caller that skipped selectionError().
     *
     * @param array<int,mixed> $selectedAddons groupId => optionId (single) | array of optionIds (multi)
     *
     * @throws \RuntimeException when the product or the selection is not valid
     */
    public function handle(
        Restaurant $restaurant,
        string $sessionId,
        Product $product,
        ?int $variantId,
        array $selectedAddons,
        int $quantity,
        ?string $notes,
    ): CartItem {
        if ((int) $product->restaurant_id !== (int) $restaurant->id) {
            throw new \RuntimeException('Produto não encontrado.');
        }

        if ($error = $this->selectionError($product, $variantId, $selectedAddons)) {
            throw new \RuntimeException($error);
        }

        $addonOptionIds = collect($selectedAddons)
            ->flatMap(fn ($value) => $this->optionIds($value))
            ->unique()
            ->values()
            ->all();

        $cart = Cart::firstOrCreate(
            ['session_id' => $sessionId, 'restaurant_id' => $restaurant->id],
            ['delivery_type' => DeliveryType::Delivery->value]
        );

        // Merge only into a line with the exact same variant AND addon set.
        $existing = CartItem::with('addons')
            ->where('cart_id', $cart->id)
            ->where('product_id', $product->id)
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
            'product_id'         => $product->id,
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

    /** @return int[] the non-empty, unique option ids of one group's selection */
    private function optionIds(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_values(array_unique(array_map('intval', array_filter($values))));
    }
}
