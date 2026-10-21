<?php

namespace Database\Factories;

use App\Models\FrontendAnnouncement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FrontendAnnouncement>
 */
class FrontendAnnouncementFactory extends Factory
{
    protected $model = FrontendAnnouncement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999),
            'category' => fake()->randomElement(['academic', 'exam', 'sports', 'general']),
            'summary' => fake()->paragraph(),
            'content' => fake()->paragraphs(3, true),
            'cover_image_path' => null,
            'attachment_path' => null,
            'attachment_name' => null,
            'is_pinned' => false,
            'is_published' => true,
            'published_at' => now(),
            'author_id' => User::factory(),
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => true,
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }
}
