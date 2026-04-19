<?php

namespace App\Livewire\Catalog;

use App\Enums\ProductAvailabilityStatus;
use App\Models\Category;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ProductList extends Component
{
    public string $search = '';
    public string $statusFilter = '';
    public string $categoryFilter = '';

    #[Computed]
    public function products()
    {
        return Product::with('category')
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
        return Category::active()->ordered()->get();
    }

    public function statuses(): array
    {
        return ProductAvailabilityStatus::cases();
    }

    public function render()
    {
        return view('livewire.catalog.product-list');
    }
}
