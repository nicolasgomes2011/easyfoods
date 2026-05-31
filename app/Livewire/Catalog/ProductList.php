<?php

namespace App\Livewire\Catalog;

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

    private function rid(): ?int
    {
        return Restaurant::query()->value('id');
    }

    #[Computed]
    public function products()
    {
        return Product::with('category')
            ->where('restaurant_id', $this->rid())
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
        $product = Product::findOrFail($productId);
        $product->update([
            'availability_status' => $product->availability_status === ProductAvailabilityStatus::Available
                ? ProductAvailabilityStatus::Unavailable
                : ProductAvailabilityStatus::Available,
        ]);
        unset($this->products);
    }

    public function delete(int $productId): void
    {
        $product = Product::findOrFail($productId);

        if ($product->orderItems()->exists()) {
            $product->update(['availability_status' => ProductAvailabilityStatus::Unavailable]);
        } else {
            $product->delete();
        }

        unset($this->products);
    }

    public function render()
    {
        return view('livewire.catalog.product-list');
    }
}
