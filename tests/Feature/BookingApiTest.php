<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingApiTest extends TestCase
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

    public function test_authenticated_student_can_create_pending_booking_with_generated_code(): void
    {
        $student = User::factory()->create();
        $room = Room::factory()->create(['status' => RoomStatus::AVAILABLE]);
        $facility = Facility::factory()->create(['global_stock' => 5, 'is_active' => true]);

        $response = $this->actingAs($student)->postJson('/api/v1/bookings', $this->payload($room, [
            'status' => BookingStatus::APPROVED->value,
            'booking_code' => 'CLIENT-CODE',
            'facilities' => [['facility_id' => $facility->id, 'quantity' => 2]],
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', BookingStatus::PENDING->value)
            ->assertJsonPath('data.room.id', $room->id)
            ->assertJsonPath('data.facilities.0.requested_quantity', 2);

        $this->assertDatabaseHas('bookings', ['user_id' => $student->id, 'room_id' => $room->id, 'status' => BookingStatus::PENDING->value]);
        $this->assertStringStartsWith('SIM-20260623-', Booking::query()->firstOrFail()->booking_code);
        $this->assertDatabaseMissing('bookings', ['booking_code' => 'CLIENT-CODE']);
    }

    public function test_guest_and_inactive_user_cannot_create_booking(): void
    {
        $room = Room::factory()->create();

        $this->postJson('/api/v1/bookings', $this->payload($room))->assertUnauthorized();

        $this->actingAs(User::factory()->inactive()->create())
            ->postJson('/api/v1/bookings', $this->payload($room))
            ->assertForbidden();
    }

    public function test_booking_date_and_time_validation_rules(): void
    {
        $student = User::factory()->create();
        $room = Room::factory()->create(['status' => RoomStatus::AVAILABLE]);

        $this->actingAs($student)->postJson('/api/v1/bookings', $this->payload($room, [
            'start_at' => '2026-06-22 10:00:00',
            'end_at' => '2026-06-22 12:00:00',
        ]))->assertStatus(409);

        $this->actingAs($student)->postJson('/api/v1/bookings', $this->payload($room, [
            'start_at' => '2026-06-21 10:00:00',
            'end_at' => '2026-06-21 12:00:00',
        ]))->assertStatus(409);

        $this->actingAs($student)->postJson('/api/v1/bookings', $this->payload($room, [
            'start_at' => '2026-06-27 10:00:00',
            'end_at' => '2026-06-27 12:00:00',
        ]))->assertStatus(409);

        $this->actingAs($student)->postJson('/api/v1/bookings', $this->payload($room, [
            'start_at' => '2026-06-28 10:00:00',
            'end_at' => '2026-06-28 12:00:00',
        ]))->assertStatus(409);

        $this->actingAs($student)->postJson('/api/v1/bookings', $this->payload($room, [
            'start_at' => '2026-06-23 12:00:00',
            'end_at' => '2026-06-23 10:00:00',
        ]))->assertUnprocessable();

        $this->actingAs($student)->postJson('/api/v1/bookings', $this->payload($room, [
            'start_at' => '2026-06-23 22:00:00',
            'end_at' => '2026-06-24 02:00:00',
        ]))->assertCreated();
    }

    public function test_room_status_and_facility_validation_rules(): void
    {
        $student = User::factory()->create();
        $unavailable = Room::factory()->create(['status' => RoomStatus::UNAVAILABLE]);
        $inactive = Room::factory()->create(['status' => RoomStatus::INACTIVE]);
        $maintenance = Room::factory()->create(['status' => RoomStatus::MAINTENANCE]);
        $room = Room::factory()->create(['status' => RoomStatus::AVAILABLE]);
        $facility = Facility::factory()->create(['global_stock' => 1]);
        $inactiveFacility = Facility::factory()->create(['is_active' => false, 'global_stock' => 5]);

        $this->actingAs($student)->postJson('/api/v1/bookings', $this->payload($unavailable))->assertStatus(409);
        $this->actingAs($student)->postJson('/api/v1/bookings', $this->payload($inactive))->assertStatus(409);
        $this->actingAs($student)->postJson('/api/v1/bookings', $this->payload($maintenance))->assertStatus(409);

        $this->actingAs($student)->postJson('/api/v1/bookings', $this->payload($room, [
            'facilities' => [
                ['facility_id' => $facility->id, 'quantity' => 1],
                ['facility_id' => $facility->id, 'quantity' => 1],
            ],
        ]))->assertUnprocessable();

        $this->actingAs($student)->postJson('/api/v1/bookings', $this->payload($room, [
            'facilities' => [['facility_id' => $inactiveFacility->id, 'quantity' => 1]],
        ]))->assertStatus(409);

        $this->actingAs($student)->postJson('/api/v1/bookings', $this->payload($room, [
            'facilities' => [['facility_id' => $facility->id, 'quantity' => 2]],
        ]))->assertStatus(409);
    }

    public function test_student_can_list_and_filter_only_own_bookings(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $room = Room::factory()->create(['name' => 'Ruang Alpha']);
        $own = Booking::factory()->create(['user_id' => $student->id, 'room_id' => $room->id, 'status' => BookingStatus::PENDING]);
        Booking::factory()->create(['user_id' => $other->id]);

        $this->actingAs($student)
            ->getJson('/api/v1/bookings?status=pending&search=Alpha&sort_by=start_at&sort_direction=asc')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_detail_and_cancel_authorization_rules(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $facility = Facility::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $student->id]);
        $approved = Booking::factory()->approved()->create(['user_id' => $student->id]);
        $booking->facilities()->attach($facility->id, ['quantity' => 2]);

        $this->actingAs($student)
            ->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.facilities.0.requested_quantity', 2);

        $this->actingAs($other)->getJson("/api/v1/bookings/{$booking->id}")->assertNotFound();
        $this->actingAs($other)->postJson("/api/v1/bookings/{$booking->id}/cancel")->assertNotFound();

        $this->actingAs($student)
            ->postJson("/api/v1/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::CANCELLED->value);

        $this->actingAs($student)->postJson("/api/v1/bookings/{$approved->id}/cancel")->assertStatus(409);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Room $room, array $overrides = []): array
    {
        return [
            'room_id' => $room->id,
            'start_at' => '2026-06-23 09:00:00',
            'end_at' => '2026-06-23 11:00:00',
            ...$overrides,
        ];
    }
}
