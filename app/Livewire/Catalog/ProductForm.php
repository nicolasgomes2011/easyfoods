<?php

namespace App\Livewire\Catalog;

use App\Enums\ProductAvailabilityStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as EnumRule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ProductForm extends Component
{
    public ?Product $product = null;

    public string $name = '';
    public string $description = '';
    public string $newCategoryName = '';
    public string $price = '';
    public string $category_id = '';
    public string $availability_status = '';
    public bool $is_featured = false;
    public int $sort_order = 0;

    public function mount(?Product $product = null): void
    {
        $this->availability_status = ProductAvailabilityStatus::Available->value;

        if (! $product?->exists) {
            return;
        }

        $this->product = $product;
        $this->name = $product->name;
        $this->description = $product->description ?? '';
        $this->price = (string) $product->price;
        $this->category_id = (string) $product->category_id;
        $this->availability_status = $product->availability_status->value;
        $this->is_featured = $product->is_featured;
        $this->sort_order = $product->sort_order;
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

    public function createCategory(): void
    {
        $this->validate(['newCategoryName' => ['required', 'string', 'max:255']]);

        $restaurantId = Restaurant::firstOrFail()->id;

        $category = Category::create([
            'restaurant_id' => $restaurantId,
            'name'          => $this->newCategoryName,
            'slug'          => Str::slug($this->newCategoryName),
            'is_active'     => true,
            'sort_order'    => 0,
        ]);

        $this->category_id = (string) $category->id;
        $this->newCategoryName = '';
        unset($this->categories);

        $this->dispatch('category-created');
    }

    public function save(): void
    {
        $restaurantId = Restaurant::firstOrFail()->id;

        $validated = $this->validate([
            'name'                => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'price'               => ['required', 'numeric', 'min:0'],
            'category_id'         => ['required', 'exists:categories,id'],
            'availability_status' => ['required', new EnumRule(ProductAvailabilityStatus::class)],
            'is_featured'         => ['boolean'],
            'sort_order'          => ['integer', 'min:0'],
        ]);

        if ($this->product) {
            $this->product->update($validated);
        } else {
            Product::create([
                ...$validated,
                'restaurant_id' => $restaurantId,
            ]);
        }

        $this->redirect(route('admin.catalog.products'), navigate: true);
    }

    public function render()
    {
        return view('livewire.catalog.product-form');
    }
}
