<?php

namespace Database\Factories;

use App\Models\PriceHistory;
use App\Models\SavedAd;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceHistory>
 */
class PriceHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'saved_ad_id' => SavedAd::factory(),
            'old_price' => fake()->randomElement([null, 10000, 20000]),
            'new_price' => fake()->randomElement([30000, 40000, 50000]),
            'changed_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
