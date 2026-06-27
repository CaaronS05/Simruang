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

class AdminBookingApiTest extends TestCase
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

    public function test_student_and_guest_cannot_access_admin_bookings(): void
    {
        $this->getJson('/api/v1/admin/bookings')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/bookings')
            ->assertForbidden();
    }

    public function test_admin_can_list_filter_search_and_view_booking_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create(['name' => 'Alice Borrower', 'campus_id' => 'C001']);
        $room = Room::factory()->create(['code' => 'R-A', 'name' => 'Ruang Alpha']);
        $booking = Booking::factory()->create(['user_id' => $student->id, 'room_id' => $room->id, 'status' => BookingStatus::PENDING]);
        Booking::factory()->approved()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/bookings?status=pending&search=Alice&sort_by=start_at&sort_direction=asc')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $booking->id)
            ->assertJsonPath('data.0.user.name', 'Alice Borrower');

        $this->actingAs($admin)
            ->getJson("/api/v1/admin/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.room.id', $room->id)
            ->assertJsonPath('data.user.id', $student->id);
    }

    public function test_admin_can_approve_pending_booking_records_reviewer_and_decreases_stock_once(): void
    {
        $admin = User::factory()->admin()->create();
        $facility = Facility::factory()->create(['global_stock' => 5]);
        $booking = Booking::factory()->create(['start_at' => '2026-06-23 09:00:00', 'end_at' => '2026-06-23 11:00:00']);
        $booking->facilities()->attach($facility->id, ['quantity' => 2]);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/bookings/{$booking->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::APPROVED->value)
            ->assertJsonPath('data.reviewer.id', $admin->id);

        $this->assertSame(3, $facility->refresh()->global_stock);
        $this->assertNotNull($booking->refresh()->reviewed_at);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/bookings/{$booking->id}/approve")
            ->assertOk();

        $this->assertSame(3, $facility->refresh()->global_stock);
    }

    public function test_approve_fails_for_insufficient_stock_unavailable_room_non_pending_past_or_conflict(): void
    {
        $admin = User::factory()->admin()->create();
        $facility = Facility::factory()->create(['global_stock' => 1]);
        $booking = Booking::factory()->create();
        $booking->facilities()->attach($facility->id, ['quantity' => 2]);

        $this->actingAs($admin)->postJson("/api/v1/admin/bookings/{$booking->id}/approve")->assertStatus(409);
        $this->assertSame(1, $facility->refresh()->global_stock);

        $unavailableRoom = Room::factory()->create(['status' => RoomStatus::UNAVAILABLE]);
        $unavailableBooking = Booking::factory()->create(['room_id' => $unavailableRoom->id]);
        $this->actingAs($admin)->postJson("/api/v1/admin/bookings/{$unavailableBooking->id}/approve")->assertStatus(409);

        $rejected = Booking::factory()->rejected()->create();
        $this->actingAs($admin)->postJson("/api/v1/admin/bookings/{$rejected->id}/approve")->assertStatus(409);

        $past = Booking::factory()->create(['start_at' => '2026-06-21 09:00:00', 'end_at' => '2026-06-21 11:00:00']);
        $this->actingAs($admin)->postJson("/api/v1/admin/bookings/{$past->id}/approve")->assertStatus(409);

        $room = Room::factory()->create();
        Booking::factory()->approved()->create(['room_id' => $room->id, 'start_at' => '2026-06-23 09:00:00', 'end_at' => '2026-06-23 11:00:00']);
        $conflicting = Booking::factory()->create(['room_id' => $room->id, 'start_at' => '2026-06-23 10:00:00', 'end_at' => '2026-06-23 12:00:00']);
        $this->actingAs($admin)->postJson("/api/v1/admin/bookings/{$conflicting->id}/approve")->assertStatus(409);
    }

    public function test_admin_can_reject_pending_booking_with_optional_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = Booking::factory()->create();
        $withoutReason = Booking::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/bookings/{$booking->id}/reject", ['rejection_reason' => 'Tidak lengkap.'])
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::REJECTED->value)
            ->assertJsonPath('data.rejection_reason', 'Tidak lengkap.');

        $this->assertSame($admin->id, $booking->refresh()->reviewed_by);
        $this->assertNotNull($booking->reviewed_at);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/bookings/{$withoutReason->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.rejection_reason', null);
    }

    public function test_rejected_booking_cannot_be_approved_and_approved_booking_cannot_be_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $rejected = Booking::factory()->rejected()->create();
        $approved = Booking::factory()->approved()->create();

        $this->actingAs($admin)->postJson("/api/v1/admin/bookings/{$rejected->id}/approve")->assertStatus(409);
        $this->actingAs($admin)->postJson("/api/v1/admin/bookings/{$approved->id}/reject")->assertStatus(409);
    }
}
