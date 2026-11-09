<?php

namespace Modules\Product\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'stock' => $this->faker->randomDigitNotNull(),
            'photo' => $this->faker->imageUrl(640, 480, 'products', true),
            'category_id' => Category::query()->inRandomOrder()->value('id')
                ?? Category::query()->firstOrCreate(['name' => 'Electronics'])->getKey(),
        ];
    }
}
