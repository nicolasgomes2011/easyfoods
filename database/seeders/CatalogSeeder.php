<?php

namespace Database\Seeders;

use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::where('slug', 'easyfoods-demo')->firstOrFail();

        // --- Categories ---
        $entradas = Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Entradas',
            'slug'          => 'entradas',
            'sort_order'    => 1,
            'is_active'     => true,
        ]);

        $principais = Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Pratos Principais',
            'slug'          => 'pratos-principais',
            'sort_order'    => 2,
            'is_active'     => true,
        ]);

        $bebidas = Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Bebidas',
            'slug'          => 'bebidas',
            'sort_order'    => 3,
            'is_active'     => true,
        ]);

        // --- Entradas ---
        $bruschetta = Product::create([
            'restaurant_id'  => $restaurant->id,
            'category_id'    => $entradas->id,
            'name'           => 'Bruschetta Clássica',
            'slug'           => 'bruschetta-classica',
            'description'    => 'Fatias de pão italiano tostado com tomate, manjericão e azeite.',
            'price'          => 22.90,
            'sort_order'     => 1,
        ]);

        $camarao = Product::create([
            'restaurant_id'  => $restaurant->id,
            'category_id'    => $entradas->id,
            'name'           => 'Camarão Empanado',
            'slug'           => 'camarao-empanado',
            'description'    => 'Camarões empanados e fritos, acompanhados de molho tártaro.',
            'price'          => 38.90,
            'sort_order'     => 2,
        ]);

        // Addon para camarão
        $molhoGroup = AddonGroup::create([
            'product_id'  => $camarao->id,
            'name'        => 'Escolha o molho',
            'required'    => true,
            'min_choices' => 1,
            'max_choices' => 1,
            'sort_order'  => 1,
            'is_active'   => true,
        ]);
        AddonOption::insert([
            ['addon_group_id' => $molhoGroup->id, 'name' => 'Molho Tártaro', 'price' => 0, 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['addon_group_id' => $molhoGroup->id, 'name' => 'Molho Ranch',   'price' => 0, 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['addon_group_id' => $molhoGroup->id, 'name' => 'Molho Rosé',   'price' => 0, 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // --- Pratos Principais ---
        $file = Product::create([
            'restaurant_id'  => $restaurant->id,
            'category_id'    => $principais->id,
            'name'           => 'Filé Mignon ao Molho Madeira',
            'slug'           => 'file-mignon-madeira',
            'description'    => '300g de filé mignon grelhado com molho madeira, acompanha arroz e fritas.',
            'price'          => 68.90,
            'sort_order'     => 1,
            'is_featured'    => true,
        ]);

        $pontoGroup = AddonGroup::create([
            'product_id'  => $file->id,
            'name'        => 'Ponto da carne',
            'required'    => true,
            'min_choices' => 1,
            'max_choices' => 1,
            'sort_order'  => 1,
            'is_active'   => true,
        ]);
        AddonOption::insert([
            ['addon_group_id' => $pontoGroup->id, 'name' => 'Mal Passado',  'price' => 0, 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['addon_group_id' => $pontoGroup->id, 'name' => 'Ao Ponto',     'price' => 0, 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['addon_group_id' => $pontoGroup->id, 'name' => 'Bem Passado',  'price' => 0, 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $extrasGroup = AddonGroup::create([
            'product_id'  => $file->id,
            'name'        => 'Adicionais',
            'required'    => false,
            'min_choices' => 0,
            'max_choices' => 3,
            'sort_order'  => 2,
            'is_active'   => true,
        ]);
        AddonOption::insert([
            ['addon_group_id' => $extrasGroup->id, 'name' => 'Queijo Provolone', 'price' => 6.00, 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['addon_group_id' => $extrasGroup->id, 'name' => 'Bacon',            'price' => 4.50, 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['addon_group_id' => $extrasGroup->id, 'name' => 'Ovo Frito',        'price' => 3.00, 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $hamburguer = Product::create([
            'restaurant_id'  => $restaurant->id,
            'category_id'    => $principais->id,
            'name'           => 'Hambúrguer Artesanal',
            'slug'           => 'hamburguer-artesanal',
            'description'    => 'Blend artesanal 180g, queijo prato, alface, tomate e molho especial.',
            'price'          => 42.90,
            'sort_order'     => 2,
            'is_featured'    => true,
        ]);

        $salmonGrelha = Product::create([
            'restaurant_id'  => $restaurant->id,
            'category_id'    => $principais->id,
            'name'           => 'Salmão Grelhado',
            'slug'           => 'salmao-grelhado',
            'description'    => 'Filé de salmão grelhado com legumes salteados e limão siciliano.',
            'price'          => 72.90,
            'sort_order'     => 3,
        ]);

        // --- Bebidas ---
        $refrigerante = Product::create([
            'restaurant_id'  => $restaurant->id,
            'category_id'    => $bebidas->id,
            'name'           => 'Refrigerante',
            'slug'           => 'refrigerante',
            'description'    => 'Coca-Cola, Guaraná ou Sprite.',
            'price'          => 7.00,
            'sort_order'     => 1,
        ]);

        // Variants for refrigerante
        \App\Models\ProductVariant::insert([
            ['product_id' => $refrigerante->id, 'name' => '350ml Lata',  'price' => 7.00,  'sort_order' => 1, 'availability_status' => 'available', 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $refrigerante->id, 'name' => '600ml PET',   'price' => 9.50,  'sort_order' => 2, 'availability_status' => 'available', 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $refrigerante->id, 'name' => '2L PET',      'price' => 14.00, 'sort_order' => 3, 'availability_status' => 'available', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Product::create([
            'restaurant_id'  => $restaurant->id,
            'category_id'    => $bebidas->id,
            'name'           => 'Suco Natural',
            'slug'           => 'suco-natural',
            'description'    => 'Laranja, limão ou maracujá. 500ml.',
            'price'          => 12.00,
            'sort_order'     => 2,
        ]);

        Product::create([
            'restaurant_id'  => $restaurant->id,
            'category_id'    => $bebidas->id,
            'name'           => 'Água Mineral',
            'slug'           => 'agua-mineral',
            'description'    => 'Água mineral sem gás 500ml.',
            'price'          => 5.00,
            'sort_order'     => 3,
        ]);
    }
}
