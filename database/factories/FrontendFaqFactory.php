<?php

namespace Database\Factories;

use App\Models\FrontendFaq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FrontendFaq>
 */
class FrontendFaqFactory extends Factory
{
    protected $model = FrontendFaq::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(['Admissions', 'Academics', 'Portal', 'General']),
            'question' => fake()->sentence().'?',
            'answer' => fake()->paragraph(),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
