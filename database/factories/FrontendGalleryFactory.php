<?php

namespace Database\Factories;

use App\Models\FrontendGallery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FrontendGallery>
 */
class FrontendGalleryFactory extends Factory
{
    protected $model = FrontendGallery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'category' => fake()->randomElement(['campus', 'sports', 'science_labs', 'cultural']),
            'image_path' => 'gallery/'.fake()->uuid().'.jpg',
            'caption' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
