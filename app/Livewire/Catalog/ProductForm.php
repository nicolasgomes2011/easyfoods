<?php

namespace App\Livewire\Catalog;

use App\Enums\ProductAvailabilityStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Restaurant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as EnumRule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductForm extends Component
{
    use WithFileUploads;

    public ?Product $product = null;

    public string $name = '';
    public string $description = '';
    public string $newCategoryName = '';
    public string $price = '';
    public string $category_id = '';
    public string $availability_status = '';
    public bool $is_featured = false;
    public int $sort_order = 0;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $photo = null;
    public bool $removeImage = false;

    /** @var array<int, array{key: string, id: ?int, name: string, price: string, availability_status: string}> */
    public array $variants = [];

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

        // Stable 'key' feeds wire:key so removing a middle row doesn't bleed
        // input state into the neighbour rows (index-based keys would).
        $this->variants = $product->variants->map(fn (ProductVariant $variant) => [
            'key'                 => 'v'.$variant->id,
            'id'                  => $variant->id,
            'name'                => $variant->name,
            'price'               => (string) $variant->price,
            'availability_status' => $variant->availability_status->value,
        ])->all();
    }

    public function addVariant(): void
    {
        $this->variants[] = [
            'key'                 => uniqid('new-'),
            'id'                  => null,
            'name'                => '',
            'price'               => '',
            'availability_status' => ProductAvailabilityStatus::Available->value,
        ];
    }

    public function removeVariant(int $index): void
    {
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    /** Authoritative image constraints — resize on save caps dimensions, not upload size. */
    private function photoRule(): array
    {
        return ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
    }

    public function updatedPhoto(): void
    {
        // Validate on selection so oversized/wrong-type files fail fast,
        // before the user fills the rest of the form.
        $this->validateOnly('photo', ['photo' => $this->photoRule()]);
    }

    private function rid(): ?int
    {
        return Restaurant::query()->value('id');
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

    public function createCategory(): void
    {
        $this->validate(['newCategoryName' => ['required', 'string', 'max:255']]);

        $restaurantId = $this->rid();

        $category = Category::create([
            'restaurant_id' => $restaurantId,
            'name'          => $this->newCategoryName,
            'slug'          => Str::slug($this->newCategoryName),
            'is_active'     => true,
            'sort_order'    => (int) Category::where('restaurant_id', $restaurantId)->max('sort_order') + 1,
        ]);

        $this->category_id = (string) $category->id;
        $this->newCategoryName = '';
        unset($this->categories);

        $this->dispatch('category-created');
    }

    public function save(): void
    {
        $restaurantId = $this->rid();

        $validated = $this->validate([
            'name'                => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'price'               => ['required', 'numeric', 'min:0'],
            // Category must belong to this restaurant — a raw exists: would accept
            // another restaurant's category id (cross-tenant write).
            'category_id'         => ['required', Rule::exists('categories', 'id')->where('restaurant_id', $restaurantId)],
            'availability_status' => ['required', new EnumRule(ProductAvailabilityStatus::class)],
            'is_featured'         => ['boolean'],
            'sort_order'          => ['integer', 'min:0'],
            'photo'               => $this->photoRule(),
            'variants'            => ['array'],
            'variants.*.name'     => ['required', 'string', 'max:100'],
            'variants.*.price'    => ['required', 'numeric', 'min:0'],
            'variants.*.availability_status' => ['required', new EnumRule(ProductAvailabilityStatus::class)],
        ]);

        $productData = Arr::only($validated, [
            'name', 'description', 'price', 'category_id',
            'availability_status', 'is_featured', 'sort_order',
        ]);

        if ($this->product) {
            $this->product->update($productData);
            $product = $this->product;
        } else {
            $product = Product::create([
                ...$productData,
                'restaurant_id' => $restaurantId,
            ]);
        }

        $this->syncVariants($product);
        $this->syncImage($product);

        $this->redirect(route('admin.catalog.products'), navigate: true);
    }

    /**
     * Upsert the editor rows and drop the removed ones. Hard-deleting a variant
     * is safe by design: order/cart FKs are nullOnDelete and order items keep
     * the frozen variant_name snapshot.
     */
    private function syncVariants(Product $product): void
    {
        $keptIds = [];

        foreach (array_values($this->variants) as $position => $row) {
            $attributes = [
                'name'                => $row['name'],
                'price'               => $row['price'],
                'availability_status' => $row['availability_status'],
                'sort_order'          => $position + 1,
            ];

            $existing = ! empty($row['id'])
                ? $product->variants()->whereKey($row['id'])->first()
                : null;

            if ($existing) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;
            } else {
                $keptIds[] = $product->variants()->create($attributes)->id;
            }
        }

        $product->variants()->whereNotIn('id', $keptIds)->delete();
    }

    private function syncImage(Product $product): void
    {
        if ($this->photo) {
            $old = $product->image;
            $path = $this->photo->store("products/{$product->restaurant_id}", 'public');
            $this->resizeToFit(Storage::disk('public')->path($path));
            $product->update(['image' => $path]);

            if ($old) {
                Storage::disk('public')->delete($old);
            }

            return;
        }

        if ($this->removeImage && $product->image) {
            Storage::disk('public')->delete($product->image);
            $product->update(['image' => null]);
        }
    }

    /**
     * Downscale in place so a phone photo (e.g. 4000x3000) never sits on disk
     * at full resolution — caps the longest side, aspect ratio preserved.
     * Uses GD (bundled with PHP) rather than adding an imaging dependency for
     * one operation. No-op if the file is already within bounds.
     */
    private function resizeToFit(string $absolutePath, int $maxDimension = 1200): void
    {
        [$width, $height, $type] = @getimagesize($absolutePath) ?: [0, 0, null];

        if (! $width || ! $height || max($width, $height) <= $maxDimension) {
            return;
        }

        $source = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => imagecreatefrompng($absolutePath),
            IMAGETYPE_WEBP => imagecreatefromwebp($absolutePath),
            default => null,
        };

        if (! $source) {
            return;
        }

        $ratio = $maxDimension / max($width, $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($type === IMAGETYPE_PNG) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        match ($type) {
            IMAGETYPE_JPEG => imagejpeg($resized, $absolutePath, 85),
            IMAGETYPE_PNG => imagepng($resized, $absolutePath, 6),
            IMAGETYPE_WEBP => imagewebp($resized, $absolutePath, 85),
            default => null,
        };

        imagedestroy($source);
        imagedestroy($resized);
    }

    public function render()
    {
        return view('livewire.catalog.product-form');
    }
}
