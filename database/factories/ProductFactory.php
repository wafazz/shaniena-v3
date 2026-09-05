<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        // Real-shaped names: the longest in the source catalogue is 32 chars.
        $name = $this->faker->randomElement([
            'Hydra Glow Cleanser',
            'Aloe Vera Gel Moisturizer 100ml',
            'Vitamin C Brightening Serum 30ml',
            'Daily UV Shield SPF50 PA+++ 50ml',
            'Collagen Night Cream',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'price_capital' => $this->faker->randomFloat(2, 10, 90),
            'weight' => $this->faker->numberBetween(50, 500),
            'length' => 100,
            'width' => 80,
            'height' => 60,
            'status' => 1,
        ];
    }

    public function named(string $name): static
    {
        return $this->state(fn () => ['name' => $name, 'slug' => Str::slug($name).'-'.uniqid()]);
    }
}
