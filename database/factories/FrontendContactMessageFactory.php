<?php

namespace Database\Factories;

use App\Models\FrontendContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FrontendContactMessage>
 */
class FrontendContactMessageFactory extends Factory
{
    protected $model = FrontendContactMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'status' => 'unread',
            'admin_notes' => null,
            'replied_at' => null,
            'replied_by' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'read',
        ]);
    }

    public function replied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'replied',
            'replied_at' => now(),
        ]);
    }
}
