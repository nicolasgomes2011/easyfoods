<?php

namespace Database\Seeders;

use App\Enums\ProductAvailabilityStatus;
use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the full menu: categories, products, size variants and addon groups.
 *
 * Idempotent: categories match on [restaurant_id, slug] (the DB unique key) and
 * products on [restaurant_id, name] — the products table has no slug since the
 * drop_slug_from_products_table migration. Variants and addons are only created
 * for products that did not exist yet, so hand edits in the admin panel survive.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::where('slug', RestaurantSeeder::SLUG)->first()
            ?? Restaurant::first();

        if (! $restaurant) {
            $this->command?->warn('CatalogSeeder skipped: no restaurant found. Run RestaurantSeeder first.');

            return;
        }

        foreach ($this->menu() as $sortOrder => $definition) {
            $category = Category::firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'slug' => Str::slug($definition['name'])],
                [
                    'name'        => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'sort_order'  => $sortOrder + 1,
                    'is_active'   => true,
                ]
            );

            foreach ($definition['products'] as $index => $product) {
                $this->seedProduct($restaurant, $category, $product, $index + 1);
            }
        }
    }

    /**
     * Creates one product with its variants and addon groups.
     * Children are only seeded on first creation to avoid clobbering manual edits.
     */
    private function seedProduct(Restaurant $restaurant, Category $category, array $data, int $sortOrder): void
    {
        $product = Product::firstOrNew(
            ['restaurant_id' => $restaurant->id, 'name' => $data['name']]
        );

        if ($product->exists) {
            return;
        }

        $product->fill([
            'category_id'         => $category->id,
            'description'         => $data['description'] ?? null,
            'price'               => $data['price'],
            'availability_status' => ProductAvailabilityStatus::Available,
            'sort_order'          => $sortOrder,
            'is_featured'         => $data['featured'] ?? false,
        ])->save();

        foreach ($data['variants'] ?? [] as $i => [$name, $price]) {
            ProductVariant::create([
                'product_id'          => $product->id,
                'name'                => $name,
                'price'               => $price,
                'availability_status' => ProductAvailabilityStatus::Available,
                'sort_order'          => $i + 1,
            ]);
        }

        foreach ($data['addons'] ?? [] as $i => $group) {
            $addonGroup = AddonGroup::create([
                'product_id'  => $product->id,
                'name'        => $group['name'],
                'required'    => $group['required'] ?? false,
                'min_choices' => $group['min'] ?? 0,
                'max_choices' => $group['max'] ?? 1,
                'sort_order'  => $i + 1,
                'is_active'   => true,
            ]);

            foreach ($group['options'] as $j => [$optionName, $optionPrice]) {
                AddonOption::create([
                    'addon_group_id' => $addonGroup->id,
                    'name'           => $optionName,
                    'price'          => $optionPrice,
                    'is_active'      => true,
                    'sort_order'     => $j + 1,
                ]);
            }
        }
    }

    /**
     * The menu definition. Order in this array becomes the category sort_order.
     *
     * @return array<int, array{name: string, description?: string, products: array<int, array>}>
     */
    private function menu(): array
    {
        $meatPoint = [
            'name'     => 'Ponto da carne',
            'required' => true,
            'min'      => 1,
            'max'      => 1,
            'options'  => [['Mal passado', 0], ['Ao ponto', 0], ['Bem passado', 0]],
        ];

        $burgerExtras = [
            'name'    => 'Adicionais',
            'min'     => 0,
            'max'     => 5,
            'options' => [
                ['Bacon', 5.00],
                ['Cheddar extra', 4.00],
                ['Ovo', 3.00],
                ['Cebola caramelizada', 3.50],
                ['Picles', 2.00],
            ],
        ];

        return [
            [
                'name'        => 'Hambúrguer',
                'description' => 'Feitos na chapa, com pão brioche artesanal.',
                'products'    => [
                    [
                        'name'        => 'X-Burger clássico',
                        'description' => 'Pão brioche, blend 160g, queijo prato, alface e tomate.',
                        'price'       => 25.90,
                        'featured'    => true,
                        'variants'    => [['Simples', 25.90], ['Duplo', 34.90]],
                        'addons'      => [$meatPoint, $burgerExtras],
                    ],
                    [
                        'name'        => 'X-Bacon',
                        'description' => 'Blend 160g, cheddar, fatias generosas de bacon e maionese da casa.',
                        'price'       => 31.90,
                        'featured'    => true,
                        'variants'    => [['Simples', 31.90], ['Duplo', 41.90]],
                        'addons'      => [$meatPoint, $burgerExtras],
                    ],
                    [
                        'name'        => 'Veggie Burger',
                        'description' => 'Hambúrguer de grão-de-bico, rúcula, tomate seco e maionese vegana.',
                        'price'       => 28.90,
                        'addons'      => [$burgerExtras],
                    ],
                ],
            ],
            [
                'name'        => 'Pizza',
                'description' => 'Massa de fermentação natural, assada em forno a lenha.',
                'products'    => [
                    [
                        'name'        => 'Pizza Margherita',
                        'description' => 'Molho de tomate italiano, muçarela de búfala e manjericão fresco.',
                        'price'       => 42.00,
                        'featured'    => true,
                        'variants'    => [['Média (6 fatias)', 42.00], ['Grande (8 fatias)', 55.00]],
                        'addons'      => [
                            [
                                'name'    => 'Borda recheada',
                                'min'     => 0,
                                'max'     => 1,
                                'options' => [['Catupiry', 8.00], ['Cheddar', 8.00]],
                            ],
                        ],
                    ],
                    [
                        'name'        => 'Pizza Calabresa',
                        'description' => 'Calabresa fatiada, cebola roxa e azeitonas.',
                        'price'       => 45.00,
                        'variants'    => [['Média (6 fatias)', 45.00], ['Grande (8 fatias)', 58.00]],
                    ],
                    [
                        'name'        => 'Pizza Quatro Queijos',
                        'description' => 'Muçarela, gorgonzola, parmesão e catupiry.',
                        'price'       => 52.00,
                        'variants'    => [['Média (6 fatias)', 52.00], ['Grande (8 fatias)', 66.00]],
                    ],
                ],
            ],
            [
                'name'        => 'Acompanhamentos',
                'description' => 'Para dividir na mesa.',
                'products'    => [
                    [
                        'name'        => 'Batata frita',
                        'description' => 'Porção de batata rústica com páprica.',
                        'price'       => 18.00,
                        'variants'    => [['Pequena', 18.00], ['Grande', 26.00]],
                        'addons'      => [
                            [
                                'name'    => 'Cobertura',
                                'min'     => 0,
                                'max'     => 2,
                                'options' => [['Cheddar e bacon', 9.00], ['Parmesão', 6.00]],
                            ],
                        ],
                    ],
                    [
                        'name'        => 'Onion rings',
                        'description' => 'Anéis de cebola empanados, com molho barbecue.',
                        'price'       => 22.00,
                    ],
                ],
            ],
            [
                'name'        => 'Bebidas',
                'description' => 'Geladas.',
                'products'    => [
                    [
                        'name'     => 'Refrigerante',
                        'price'    => 7.00,
                        'variants' => [['Lata 350ml', 7.00], ['Garrafa 600ml', 11.00], ['Garrafa 2L', 15.00]],
                    ],
                    [
                        'name'     => 'Suco natural',
                        'price'    => 12.00,
                        'variants' => [['Laranja 500ml', 12.00], ['Abacaxi com hortelã 500ml', 13.00]],
                    ],
                    [
                        'name'        => 'Cerveja artesanal',
                        'description' => 'IPA local, 500ml.',
                        'price'       => 19.00,
                    ],
                    [
                        'name'  => 'Água mineral 500ml',
                        'price' => 5.00,
                    ],
                ],
            ],
            [
                'name'        => 'Sobremesas',
                'description' => 'Para fechar a conta.',
                'products'    => [
                    [
                        'name'        => 'Petit gateau',
                        'description' => 'Bolo de chocolate com sorvete de creme.',
                        'price'       => 24.00,
                        'featured'    => true,
                    ],
                    [
                        'name'        => 'Cheesecake de frutas vermelhas',
                        'price'       => 21.00,
                    ],
                ],
            ],
        ];
    }
}
