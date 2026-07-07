<?php

namespace Tests\Feature\Catalog;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\UserRole;
use App\Livewire\Catalog\ProductForm;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Variants editor (repeatable rows synced create/update/delete) and product
 * image upload, including the server-side resize applied on save.
 */
class ProductFormTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Lanches',
            'slug'          => 'lanches',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function product(): Product
    {
        return Product::create([
            'restaurant_id'       => $this->restaurant->id,
            'category_id'         => $this->category->id,
            'name'                => 'Pizza Margherita',
            'price'               => 40.00,
            'availability_status' => ProductAvailabilityStatus::Available,
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);
    }

    private function variantRow(?int $id, string $name, string $price): array
    {
        return [
            'key'                 => $id ? "v{$id}" : uniqid('new-'),
            'id'                  => $id,
            'name'                => $name,
            'price'               => $price,
            'availability_status' => ProductAvailabilityStatus::Available->value,
        ];
    }

    public function test_variants_are_created_with_a_new_product(): void
    {
        Livewire::test(ProductForm::class)
            ->set('name', 'Pizza Calabresa')
            ->set('price', '45.00')
            ->set('category_id', (string) $this->category->id)
            ->set('variants', [
                $this->variantRow(null, 'Média', '45.00'),
                $this->variantRow(null, 'Grande', '55.00'),
            ])
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::where('name', 'Pizza Calabresa')->firstOrFail();
        $this->assertSame(
            [['Média', 1], ['Grande', 2]],
            $product->variants->map(fn ($v) => [$v->name, $v->sort_order])->all()
        );
    }

    public function test_variant_sync_updates_removes_and_adds_rows(): void
    {
        $product = $this->product();
        $keep   = $product->variants()->create(['name' => 'Média', 'price' => 40, 'availability_status' => 'available', 'sort_order' => 1]);
        $remove = $product->variants()->create(['name' => 'Grande', 'price' => 50, 'availability_status' => 'available', 'sort_order' => 2]);

        Livewire::test(ProductForm::class, ['product' => $product])
            ->set('variants', [
                $this->variantRow($keep->id, 'Média Renomeada', '42.00'),
                $this->variantRow(null, 'Família', '65.00'),
            ])
            ->call('save')
            ->assertHasNoErrors();

        $names = $product->fresh()->variants->pluck('name')->all();
        $this->assertSame(['Média Renomeada', 'Família'], $names);
        $this->assertDatabaseMissing('product_variants', ['id' => $remove->id]);
        $this->assertSame('42.00', (string) $keep->fresh()->price);
    }

    public function test_deleting_a_variant_keeps_the_frozen_order_snapshot(): void
    {
        $product = $this->product();
        $variant = $product->variants()->create(['name' => 'Grande', 'price' => 55, 'availability_status' => 'available', 'sort_order' => 1]);

        $order = Order::factory()->for($this->restaurant)->create();
        $item = OrderItem::create([
            'order_id'           => $order->id,
            'product_id'         => $product->id,
            'product_variant_id' => $variant->id,
            'product_name'       => $product->name,
            'variant_name'       => 'Grande',
            'unit_price'         => 55.00,
            'quantity'           => 1,
            'subtotal'           => 55.00,
        ]);

        Livewire::test(ProductForm::class, ['product' => $product])
            ->set('variants', []) // all rows removed in the editor
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);

        $item->refresh();
        $this->assertSame('Grande', $item->variant_name); // snapshot intact
        $this->assertNull($item->product_variant_id);     // FK nulled, not cascaded
    }

    public function test_variant_rows_require_name_and_numeric_price(): void
    {
        Livewire::test(ProductForm::class)
            ->set('name', 'Pizza Quatro Queijos')
            ->set('price', '50.00')
            ->set('category_id', (string) $this->category->id)
            ->set('variants', [$this->variantRow(null, '', 'abc')])
            ->call('save')
            ->assertHasErrors(['variants.0.name', 'variants.0.price']);

        $this->assertDatabaseMissing('products', ['name' => 'Pizza Quatro Queijos']);
    }

    public function test_image_upload_stores_the_file_and_replacement_deletes_the_old_one(): void
    {
        Storage::fake('public');
        $product = $this->product();

        Livewire::test(ProductForm::class, ['product' => $product])
            ->set('photo', UploadedFile::fake()->image('pizza.jpg', 800, 600))
            ->call('save')
            ->assertHasNoErrors();

        $firstPath = $product->fresh()->image;
        $this->assertNotNull($firstPath);
        $this->assertStringStartsWith("products/{$this->restaurant->id}/", $firstPath);
        Storage::disk('public')->assertExists($firstPath);

        // Replace: new file lands, the old one is cleaned up.
        Livewire::test(ProductForm::class, ['product' => $product->fresh()])
            ->set('photo', UploadedFile::fake()->image('nova.png', 800, 600))
            ->call('save')
            ->assertHasNoErrors();

        $secondPath = $product->fresh()->image;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertExists($secondPath);
        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_oversized_image_is_downscaled_to_the_max_dimension(): void
    {
        Storage::fake('public');
        $product = $this->product();

        Livewire::test(ProductForm::class, ['product' => $product])
            ->set('photo', UploadedFile::fake()->image('grande.jpg', 2400, 1800))
            ->call('save')
            ->assertHasNoErrors();

        $path = $product->fresh()->image;
        [$width, $height] = getimagesize(Storage::disk('public')->path($path));

        $this->assertSame(1200, $width);
        $this->assertSame(900, $height); // aspect ratio (4:3) preserved
    }

    public function test_image_within_bounds_is_left_untouched(): void
    {
        Storage::fake('public');
        $product = $this->product();

        Livewire::test(ProductForm::class, ['product' => $product])
            ->set('photo', UploadedFile::fake()->image('pequena.jpg', 400, 300))
            ->call('save')
            ->assertHasNoErrors();

        $path = $product->fresh()->image;
        [$width, $height] = getimagesize(Storage::disk('public')->path($path));

        $this->assertSame(400, $width);
        $this->assertSame(300, $height);
    }

    public function test_image_rejects_wrong_type_and_oversized_files(): void
    {
        Storage::fake('public');
        $product = $this->product();

        Livewire::test(ProductForm::class, ['product' => $product])
            ->set('photo', UploadedFile::fake()->create('cardapio.pdf', 300, 'application/pdf'))
            ->assertHasErrors(['photo']);

        Livewire::test(ProductForm::class, ['product' => $product])
            ->set('photo', UploadedFile::fake()->image('gigante.jpg')->size(3000)) // > 2MB
            ->assertHasErrors(['photo']);

        $this->assertNull($product->fresh()->image);
    }

    public function test_remove_image_checkbox_clears_column_and_deletes_file(): void
    {
        Storage::fake('public');
        $product = $this->product();

        $path = UploadedFile::fake()->image('antiga.jpg')->store("products/{$this->restaurant->id}", 'public');
        $product->update(['image' => $path]);

        Livewire::test(ProductForm::class, ['product' => $product->fresh()])
            ->set('removeImage', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($product->fresh()->image);
        Storage::disk('public')->assertMissing($path);
    }
}
