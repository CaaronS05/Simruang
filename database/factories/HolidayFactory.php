<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'holiday_date' => fake()->unique()->dateTimeBetween('now', '+1 year'),
            'name' => fake()->words(3, true),
            'source' => 'factory',
            'synced_at' => now(),
        ];
    }
}
