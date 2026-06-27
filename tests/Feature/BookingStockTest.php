<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingStockTest extends TestCase
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

    public function test_approved_booking_reduces_stock_once_and_never_negative(): void
    {
        $admin = User::factory()->admin()->create();
        $facility = Facility::factory()->create(['global_stock' => 2]);
        $booking = Booking::factory()->create();
        $booking->facilities()->attach($facility->id, ['quantity' => 2]);

        $this->actingAs($admin)->postJson("/api/v1/admin/bookings/{$booking->id}/approve")->assertOk();
        $this->assertSame(0, $facility->refresh()->global_stock);

        $this->actingAs($admin)->postJson("/api/v1/admin/bookings/{$booking->id}/approve")->assertOk();
        $this->assertSame(0, $facility->refresh()->global_stock);
    }

    public function test_failed_approval_and_rejection_do_not_change_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $facility = Facility::factory()->create(['global_stock' => 1]);
        $failedBooking = Booking::factory()->create();
        $rejectedBooking = Booking::factory()->create();
        $failedBooking->facilities()->attach($facility->id, ['quantity' => 2]);
        $rejectedBooking->facilities()->attach($facility->id, ['quantity' => 1]);

        $this->actingAs($admin)->postJson("/api/v1/admin/bookings/{$failedBooking->id}/approve")->assertStatus(409);
        $this->assertSame(1, $facility->refresh()->global_stock);

        $this->actingAs($admin)->postJson("/api/v1/admin/bookings/{$rejectedBooking->id}/reject")->assertOk();
        $this->assertSame(1, $facility->refresh()->global_stock);
    }
}
