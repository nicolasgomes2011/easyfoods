<?php

namespace App\Livewire\Concerns;

use App\Actions\Orders\AddOrderItem;
use App\Actions\Orders\RemoveOrderItem;
use App\Exceptions\OrderNotEditable;
use App\Models\Product;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;

/**
 * Add / remove items of an open dine-in order on the order screen (orders/show).
 *
 * The using component provides `public Order $order`, `public ?string $statusMessage`
 * and `reloadOrder()`. Every action re-checks the `editItems` policy; the business
 * rules (and their messages) live in AddOrderItem / RemoveOrderItem.
 */
trait EditsOrderItems
{
    // Add form (ids/quantity cast on submit: selects and number inputs send strings).
    public bool $showAddItem = false;
    public $newProductId = null;
    public $newVariantId = null;
    /** @var array<int,mixed> groupId => optionId (single) | optionIds (multi) */
    public array $newAddons = [];
    public $newQuantity = 1;
    public string $newNotes = '';

    // Removal (reason required).
    public ?int $removingItemId = null;
    public string $removeReason = '';

    /** Orderable products of the order's restaurant, loaded only while the add form is open. */
    #[Computed]
    public function menuProducts()
    {
        if (! $this->showAddItem) {
            return collect();
        }

        return Product::where('restaurant_id', $this->order->restaurant_id)
            ->notArchived()
            ->available()
            ->with([
                'variants'    => fn ($query) => $query->available()->orderBy('sort_order'),
                'addonGroups' => fn ($query) => $query->active()->orderBy('sort_order')->with('activeOptions'),
            ])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedProduct(): ?Product
    {
        return filled($this->newProductId)
            ? $this->menuProducts->firstWhere('id', (int) $this->newProductId)
            : null;
    }

    public function openAddItem(): void
    {
        Gate::authorize('editItems', $this->order);

        $this->resetAddItemForm();
        $this->showAddItem = true;
    }

    public function closeAddItem(): void
    {
        $this->resetAddItemForm();
    }

    public function updatedNewProductId(): void
    {
        $this->newVariantId = null;
        $this->newAddons = [];
        unset($this->selectedProduct);

        // Multi-choice groups bind to arrays (checkboxes).
        foreach ($this->selectedProduct?->addonGroups ?? [] as $group) {
            if ($group->allowsMultiple()) {
                $this->newAddons[$group->id] = [];
            }
        }
    }

    public function addItem(AddOrderItem $action): void
    {
        Gate::authorize('editItems', $this->order);

        $this->validate([
            'newProductId' => ['required', 'integer'],
            'newVariantId' => ['nullable', 'integer'],
            'newQuantity'  => ['required', 'integer', 'min:1', 'max:'.AddOrderItem::MAX_QUANTITY],
            'newNotes'     => ['nullable', 'string', 'max:255'],
            'newAddons'    => ['array'],
        ], [], [
            'newProductId' => 'produto',
            'newVariantId' => 'opção',
            'newQuantity'  => 'quantidade',
            'newNotes'     => 'observação',
        ]);

        $product = Product::where('restaurant_id', $this->order->restaurant_id)->find((int) $this->newProductId);

        if ($product === null) {
            $this->addError('newProductId', 'Produto não encontrado.');

            return;
        }

        try {
            $item = $action->execute(
                $this->order,
                $product,
                filled($this->newVariantId) ? (int) $this->newVariantId : null,
                $this->newAddons,
                (int) $this->newQuantity,
                $this->newNotes,
                auth()->user(),
            );
        } catch (OrderNotEditable $e) {
            $this->addError('newItem', $e->getMessage());

            return;
        }

        $this->resetAddItemForm();
        $this->reloadOrder();
        $this->statusMessage = "Item adicionado: {$item->quantity}× {$item->displayName()}.";
    }

    public function startRemoving(int $itemId): void
    {
        Gate::authorize('editItems', $this->order);

        $this->resetErrorBag('removeReason');
        $this->removeReason = '';
        $this->removingItemId = $itemId;
    }

    public function cancelRemoving(): void
    {
        $this->reset('removingItemId', 'removeReason');
        $this->resetErrorBag('removeReason');
    }

    public function removeItem(RemoveOrderItem $action): void
    {
        Gate::authorize('editItems', $this->order);

        $this->validate(
            [
                'removingItemId' => ['required', 'integer'],
                'removeReason'   => ['required', 'string', 'min:'.RemoveOrderItem::MIN_REASON_LENGTH, 'max:500'],
            ],
            [],
            ['removeReason' => 'motivo'],
        );

        try {
            $action->execute($this->order, (int) $this->removingItemId, $this->removeReason, auth()->user());
        } catch (OrderNotEditable $e) {
            $this->addError('removeReason', $e->getMessage());

            return;
        }

        $this->reset('removingItemId', 'removeReason');
        $this->reloadOrder();
        $this->statusMessage = 'Item removido do pedido.';
    }

    private function resetAddItemForm(): void
    {
        $this->reset('showAddItem', 'newProductId', 'newVariantId', 'newAddons', 'newQuantity', 'newNotes');
        $this->resetErrorBag(['newProductId', 'newVariantId', 'newQuantity', 'newNotes', 'newItem']);
        unset($this->menuProducts, $this->selectedProduct);
    }
}
