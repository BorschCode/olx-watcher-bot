<?php

namespace Database\Factories;

use App\Models\SavedAd;
use App\Models\Watcher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedAd>
 */
class SavedAdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'olx_id' => fake()->unique()->randomNumber(),
            'source' => 'olx',
            'telegram_chat_id' => fake()->numerify('chat-##########'),
            'title' => fake()->sentence(),
            'url' => fake()->url(),
            'price' => fake()->randomElement([null, 10000, 30000]),
            'track_price' => false,
            'watcher_id' => Watcher::factory()->create(['is_active' => true, 'telegram_chat_id' => 'chat-0000000000']),
        ];
    }
}
