<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Enums\UserRole;
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
        $startAt = now()->addWeekday()->setTime(9, 0);

        return [
            'booking_code' => 'SIM-'.$startAt->format('Ymd').'-'.Str::upper(Str::random(6)),
            'user_id' => User::factory()->state(['role' => UserRole::STUDENT, 'is_active' => true]),
            'room_id' => Room::factory()->state(['status' => RoomStatus::AVAILABLE]),
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addHours(2),
            'status' => BookingStatus::PENDING,
            'document_path' => null,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'cancelled_at' => null,
            'completed_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::REJECTED,
            'reviewed_at' => now(),
            'rejection_reason' => 'Ditolak.',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }
}
