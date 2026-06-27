<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startAt = fake()->dateTimeBetween('+1 day', '+1 month');

        return [
            'booking_code' => 'BK-'.Str::upper(Str::random(10)),
            'user_id' => User::factory(),
            'room_id' => Room::factory(),
            'start_at' => $startAt,
            'end_at' => (clone $startAt)->modify('+2 hours'),
            'status' => BookingStatus::PENDING,
            'document_path' => null,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'cancelled_at' => null,
            'completed_at' => null,
        ];
    }
}
