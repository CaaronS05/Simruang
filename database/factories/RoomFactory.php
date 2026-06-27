<?php

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('R-###'),
            'name' => fake()->words(3, true),
            'building' => fake()->randomElement(['Gedung A', 'Gedung B', 'Gedung C']),
            'floor' => (string) fake()->numberBetween(1, 5),
            'capacity' => fake()->numberBetween(10, 200),
            'description' => fake()->optional()->sentence(),
            'photo_path' => null,
            'status' => RoomStatus::AVAILABLE,
        ];
    }
}
