<?php

namespace Database\Seeders;

use App\Enums\ProductAvailabilityStatus;
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

        $categoria = Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Pratos Principais',
            'slug'          => 'pratos-principais',
            'sort_order'    => 1,
            'is_active'     => true,
        ]);

        $produto = Product::create([
            'restaurant_id'       => $restaurant->id,
            'category_id'         => $categoria->id,
            'name'                => 'Hambúrguer Artesanal',
            'slug'                => 'hamburguer-artesanal',
            'description'         => 'Blend artesanal 180g, queijo prato, alface, tomate e molho especial.',
            'price'               => 42.90,
            'availability_status' => ProductAvailabilityStatus::Available,
            'sort_order'          => 1,
            'is_featured'         => true,
        ]);

        $pontoGroup = AddonGroup::create([
            'product_id'  => $produto->id,
            'name'        => 'Ponto da carne',
            'required'    => true,
            'min_choices' => 1,
            'max_choices' => 1,
            'sort_order'  => 1,
            'is_active'   => true,
        ]);

        AddonOption::insert([
            ['addon_group_id' => $pontoGroup->id, 'name' => 'Mal Passado', 'price' => 0, 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['addon_group_id' => $pontoGroup->id, 'name' => 'Ao Ponto',    'price' => 0, 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['addon_group_id' => $pontoGroup->id, 'name' => 'Bem Passado', 'price' => 0, 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
