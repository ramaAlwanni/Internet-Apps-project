<?php

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Category\Models\Category;
use Modules\Product\Models\Product;

class ProductDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $category = Category::firstOrCreate([
            'name' => 'Electronics'
        ]);

        Product::create([
            'name' => 'Smartphone X',
            'description' => 'Latest model with high performance and great camera.',
            'price' => 599.99,
            'photo' => 'photos/phone.jpg',
            'stock' => 25,
            'category_id' => $category->id,
        ]);

        Product::create([
            'name' => 'Wireless Headphones',
            'description' => 'Noise-cancelling over-ear wireless headphones.',
            'price' => 129.99,
            'photo' => 'photos/headphones.jpg',
            'stock' => 50,
            'category_id' => $category->id,
        ]);

        Product::create([
            'name' => 'Mechanical Keyboard',
            'description' => 'RGB backlit mechanical keyboard for gamers and developers.',
            'price' => 85.50,
            'photo' => 'photos/keyboard.jpg',
            'stock' => 15,
            'category_id' => $category->id,
        ]);
    }
}
