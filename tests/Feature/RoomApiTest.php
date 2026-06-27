<?php

namespace Tests\Feature;

use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoomApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_student_can_list_rooms(): void
    {
        Room::factory()->count(2)->create();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/rooms')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_guest_cannot_list_rooms(): void
    {
        $this->getJson('/api/v1/rooms')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_rooms_can_be_searched_filtered_and_sorted(): void
    {
        Room::factory()->create(['code' => 'LAB-1', 'name' => 'Laboratorium Komputer', 'building' => 'Gedung B', 'capacity' => 40, 'status' => RoomStatus::AVAILABLE]);
        Room::factory()->create(['code' => 'AUD-1', 'name' => 'Aula Besar', 'building' => 'Gedung A', 'capacity' => 200, 'status' => RoomStatus::MAINTENANCE]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/rooms?search=Laboratorium')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'LAB-1');

        $this->actingAs($user)
            ->getJson('/api/v1/rooms?status=maintenance&building=Gedung%20A&minimum_capacity=100&maximum_capacity=250')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'AUD-1');

        $this->actingAs($user)
            ->getJson('/api/v1/rooms?sort_by=capacity&sort_direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'LAB-1');

        $this->actingAs($user)
            ->getJson('/api/v1/rooms?sort_by=password')
            ->assertUnprocessable();
    }

    public function test_room_detail_includes_permanent_facilities_and_quantity(): void
    {
        $room = Room::factory()->create();
        $facility = Facility::factory()->create();
        $room->facilities()->attach($facility->id, ['quantity' => 3]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/rooms/{$room->id}")
            ->assertOk()
            ->assertJsonPath('data.facilities.0.id', $facility->id)
            ->assertJsonPath('data.facilities.0.quantity', 3);
    }

    public function test_student_cannot_create_room(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/admin/rooms', $this->roomPayload())
            ->assertForbidden();
    }

    public function test_admin_can_create_room_and_validation_rejects_duplicate_code(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/rooms', $this->roomPayload(['code' => 'ROOM-1']))
            ->assertCreated()
            ->assertJsonPath('data.code', 'ROOM-1');

        $this->assertDatabaseHas('rooms', ['code' => 'ROOM-1']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/rooms', $this->roomPayload(['code' => 'ROOM-1']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/rooms', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'building', 'capacity', 'status']);
    }

    public function test_admin_can_update_room_and_upload_or_replace_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $room = Room::factory()->create(['photo_path' => 'rooms/old.jpg']);
        Storage::disk('public')->put('rooms/old.jpg', 'old');

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/rooms', [
                ...$this->roomPayload(['code' => 'PHOTO-1']),
                'photo' => UploadedFile::fake()->image('room.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'PHOTO-1');

        $created = Room::query()->where('code', 'PHOTO-1')->firstOrFail();
        Storage::disk('public')->assertExists($created->photo_path);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/rooms/{$room->id}", [
                ...$this->roomPayload(['code' => 'UPDATED-1', 'name' => 'Updated Room']),
                'photo' => UploadedFile::fake()->image('new-room.png'),
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Room');

        Storage::disk('public')->assertMissing('rooms/old.jpg');
        Storage::disk('public')->assertExists($room->refresh()->photo_path);
    }

    public function test_admin_can_delete_unused_room_but_not_room_with_bookings(): void
    {
        $admin = User::factory()->admin()->create();
        $unusedRoom = Room::factory()->create();
        $bookedRoom = Room::factory()->create();
        Booking::factory()->create(['room_id' => $bookedRoom->id]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/rooms/{$bookedRoom->id}")
            ->assertStatus(409);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/rooms/{$unusedRoom->id}")
            ->assertOk();

        $this->assertDatabaseMissing('rooms', ['id' => $unusedRoom->id]);
    }

    public function test_admin_can_sync_permanent_room_facilities(): void
    {
        $admin = User::factory()->admin()->create();
        $room = Room::factory()->create();
        $facility = Facility::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/rooms/{$room->id}/facilities/sync", [
                'facilities' => [
                    ['facility_id' => $facility->id, 'quantity' => 2],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.facilities.0.quantity', 2);

        $this->assertDatabaseHas('facility_room', ['room_id' => $room->id, 'facility_id' => $facility->id, 'quantity' => 2]);
    }

    public function test_sync_rejects_duplicate_and_inactive_facilities(): void
    {
        $admin = User::factory()->admin()->create();
        $room = Room::factory()->create();
        $facility = Facility::factory()->create();
        $inactiveFacility = Facility::factory()->create(['is_active' => false]);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/rooms/{$room->id}/facilities/sync", [
                'facilities' => [
                    ['facility_id' => $facility->id, 'quantity' => 1],
                    ['facility_id' => $facility->id, 'quantity' => 2],
                ],
            ])
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/rooms/{$room->id}/facilities/sync", [
                'facilities' => [
                    ['facility_id' => $inactiveFacility->id, 'quantity' => 1],
                ],
            ])
            ->assertUnprocessable();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function roomPayload(array $overrides = []): array
    {
        return [
            'code' => 'ROOM-'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Ruang Test',
            'building' => 'Gedung A',
            'floor' => '1',
            'capacity' => 30,
            'description' => 'Deskripsi ruangan',
            'status' => RoomStatus::AVAILABLE->value,
            ...$overrides,
        ];
    }
}
