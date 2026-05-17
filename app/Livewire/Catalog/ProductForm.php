<?php

namespace App\Livewire\Catalog;

use App\Enums\ProductAvailabilityStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ProductForm extends Component
{
    public ?Product $product = null;

    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public string $price = '';
    public string $category_id = '';
    public string $availability_status = '';
    public bool $is_featured = false;
    public int $sort_order = 0;

    public function mount(?Product $product = null): void
    {
        $this->availability_status = ProductAvailabilityStatus::Available->value;

        if (! $product) {
            return;
        }

        $this->product = $product;
        $this->name = $product->name;
        $this->slug = $product->slug;
        $this->description = $product->description ?? '';
        $this->price = (string) $product->price;
        $this->category_id = (string) $product->category_id;
        $this->availability_status = $product->availability_status->value;
        $this->is_featured = $product->is_featured;
        $this->sort_order = $product->sort_order;
    }

    public function updatedName(string $value): void
    {
        if (! $this->product) {
            $this->slug = Str::slug($value);
        }
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

    public function save(): void
    {
        $restaurantId = Restaurant::firstOrFail()->id;

        $validated = $this->validate([
            'name'                => ['required', 'string', 'max:255'],
            'slug'                => ['required', 'string', 'max:255',
                Rule::unique('products', 'slug')
                    ->where('restaurant_id', $restaurantId)
                    ->ignore($this->product?->id),
            ],
            'description'         => ['nullable', 'string'],
            'price'               => ['required', 'numeric', 'min:0'],
            'category_id'         => ['required', 'exists:categories,id'],
            'availability_status' => ['required', Rule::enum(ProductAvailabilityStatus::class)],
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
