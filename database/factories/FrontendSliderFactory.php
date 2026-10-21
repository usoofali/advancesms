<?php

namespace Database\Factories;

use App\Models\FrontendSlider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FrontendSlider>
 */
class FrontendSliderFactory extends Factory
{
    protected $model = FrontendSlider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'subtitle' => fake()->sentence(8),
            'badge' => fake()->randomElement(['Admissions Open', 'Academic Excellence', 'Welcome']),
            'primary_button_text' => 'Learn More',
            'primary_button_url' => '/about',
            'secondary_button_text' => 'Contact Us',
            'secondary_button_url' => '/contact',
            'image_path' => null,
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
