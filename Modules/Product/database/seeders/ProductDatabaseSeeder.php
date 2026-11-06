<?php

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;

class ProductDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['phone.jpg', 'headphones.jpg', 'keyboard.jpg'] as $image) {
            $source = base_path("Modules/Product/resources/images/{$image}");

            if (! is_file($source)) {
                throw new RuntimeException("Seed product image not found: {$source}");
            }

            if (Storage::disk('public')->putFileAs('products', new File($source), $image) === false) {
                throw new RuntimeException("Failed to copy seed product image: {$image}");
            }
        }

        $category = Category::firstOrCreate([
            'name' => 'Electronics'
        ]);

        Product::create([
            'name' => 'Smartphone X',
            'description' => 'Latest model with high performance and great camera.',
            'price' => 599.99,
            'photo' => 'products/phone.jpg',
            'stock' => 25,
            'category_id' => $category->id,
        ]);

        Product::create([
            'name' => 'Wireless Headphones',
            'description' => 'Noise-cancelling over-ear wireless headphones.',
            'price' => 129.99,
            'photo' => 'products/headphones.jpg',
            'stock' => 50,
            'category_id' => $category->id,
        ]);

        Product::create([
            'name' => 'Mechanical Keyboard',
            'description' => 'RGB backlit mechanical keyboard for gamers and developers.',
            'price' => 85.50,
            'photo' => 'products/keyboard.jpg',
            'stock' => 15,
            'category_id' => $category->id,
        ]);
    }
}
