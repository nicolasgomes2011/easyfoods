<?php

namespace App\Livewire\Catalog;

use App\Enums\OrderStatus;
use App\Enums\ProductAvailabilityStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ProductList extends Component
{
    public string $search = '';
    public string $statusFilter = '';
    public string $categoryFilter = '';
    public bool $showArchived = false;

    private function rid(): ?int
    {
        return Restaurant::query()->value('id');
    }

    #[Computed]
    public function products()
    {
        return Product::with('category')
            ->where('restaurant_id', $this->rid())
            ->when($this->showArchived,
                fn ($q) => $q->whereNotNull('archived_at'),
                fn ($q) => $q->whereNull('archived_at'))
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('availability_status', $this->statusFilter))
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function categories()
    {
        return Category::where('restaurant_id', $this->rid())->active()->ordered()->get();
    }

    public function statuses(): array
    {
        return ProductAvailabilityStatus::cases();
    }

    public function toggleAvailability(int $productId): void
    {
        // Scoped lookup: a bare findOrFail would act on another restaurant's product (IDOR).
        $product = Product::where('restaurant_id', $this->rid())->findOrFail($productId);
        $product->update([
            'availability_status' => $product->availability_status === ProductAvailabilityStatus::Available
                ? ProductAvailabilityStatus::Unavailable
                : ProductAvailabilityStatus::Available,
        ]);
        unset($this->products);
    }

    public function archive(int $productId): void
    {
        $product = Product::where('restaurant_id', $this->rid())->findOrFail($productId);
        $product->update(['archived_at' => now()]);
        unset($this->products);
    }

    public function unarchive(int $productId): void
    {
        $product = Product::where('restaurant_id', $this->rid())->findOrFail($productId);
        $product->update(['archived_at' => null]);
        unset($this->products);
    }

    public function delete(int $productId): void
    {
        $product = Product::where('restaurant_id', $this->rid())->findOrFail($productId);

        // Block only while the product sits on an order still in play. Closed
        // orders are safe by design: items keep frozen name/price snapshots and
        // the FK nulls out on delete, so history and reports survive.
        $openStatuses = array_map(
            fn (OrderStatus $status) => $status->value,
            array_filter(OrderStatus::cases(), fn (OrderStatus $status) => $status->isActive())
        );

        $hasOpenOrders = $product->orderItems()
            ->whereHas('order', fn ($q) => $q->whereIn('status', $openStatuses))
            ->exists();

        if ($hasOpenOrders) {
            $this->addError('delete', "Não é possível excluir \"{$product->name}\": há pedidos em andamento com este item. Você pode arquivá-lo.");
            return;
        }

        $product->delete();
        unset($this->products);
    }

    public function render()
    {
        return view('livewire.catalog.product-list');
    }
}
