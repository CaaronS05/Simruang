<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingConflictTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-22 08:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_overlapping_pending_or_approved_booking_is_rejected(): void
    {
        $student = User::factory()->create();
        $room = Room::factory()->create();
        Booking::factory()->create([
            'room_id' => $room->id,
            'start_at' => '2026-06-23 09:00:00',
            'end_at' => '2026-06-23 11:00:00',
            'status' => BookingStatus::PENDING,
        ]);

        $this->actingAs($student)->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'start_at' => '2026-06-23 10:00:00',
            'end_at' => '2026-06-23 12:00:00',
        ])->assertStatus(409);
    }

    public function test_adjacent_booking_times_are_allowed(): void
    {
        $student = User::factory()->create();
        $room = Room::factory()->create();
        Booking::factory()->approved()->create([
            'room_id' => $room->id,
            'start_at' => '2026-06-23 09:00:00',
            'end_at' => '2026-06-23 11:00:00',
        ]);

        $this->actingAs($student)->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'start_at' => '2026-06-23 11:00:00',
            'end_at' => '2026-06-23 12:00:00',
        ])->assertCreated();
    }

    public function test_rejected_cancelled_and_completed_bookings_do_not_block_schedule(): void
    {
        $student = User::factory()->create();
        $room = Room::factory()->create();

        foreach ([BookingStatus::REJECTED, BookingStatus::CANCELLED, BookingStatus::COMPLETED] as $index => $status) {
            Booking::factory()->create([
                'room_id' => $room->id,
                'start_at' => '2026-06-23 09:00:00',
                'end_at' => '2026-06-23 11:00:00',
                'status' => $status,
            ]);

            $this->actingAs($student)->postJson('/api/v1/bookings', [
                'room_id' => $room->id,
                'start_at' => '2026-06-23 '.(12 + $index).':00:00',
                'end_at' => '2026-06-23 '.(13 + $index).':00:00',
            ])->assertCreated();
        }
    }
}
