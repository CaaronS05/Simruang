<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Facility;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FacilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_access_admin_facility_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/facilities')
            ->assertForbidden();
    }

    public function test_admin_can_list_facilities_and_search_filter_sort(): void
    {
        Facility::factory()->create(['name' => 'Proyektor Besar', 'condition' => 'good', 'global_stock' => 5, 'is_active' => true]);
        Facility::factory()->create(['name' => 'Mikrofon Rusak', 'condition' => 'damaged', 'global_stock' => 1, 'is_active' => false]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/facilities?search=Proyektor&condition=good&is_active=1&sort_by=global_stock&sort_direction=desc')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Proyektor Besar');
    }

    public function test_admin_can_create_facility_and_validation_rejects_invalid_values(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/facilities', $this->facilityPayload(['name' => 'Laptop']))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Laptop');

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/facilities', $this->facilityPayload(['name' => 'Laptop']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/facilities', $this->facilityPayload(['name' => 'Bad Stock', 'global_stock' => -1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('global_stock');

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/facilities', $this->facilityPayload(['name' => 'Bad Condition', 'condition' => 'broken']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('condition');
    }

    public function test_uploaded_facility_photo_is_stored_and_replaced_photo_is_removed(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $facility = Facility::factory()->create(['photo_path' => 'facilities/old.jpg']);
        Storage::disk('public')->put('facilities/old.jpg', 'old');

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/facilities', [
                ...$this->facilityPayload(['name' => 'Photo Facility']),
                'photo' => UploadedFile::fake()->image('facility.jpg'),
            ])
            ->assertCreated();

        $created = Facility::query()->where('name', 'Photo Facility')->firstOrFail();
        Storage::disk('public')->assertExists($created->photo_path);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/facilities/{$facility->id}", [
                ...$this->facilityPayload(['name' => 'Updated Facility']),
                'photo' => UploadedFile::fake()->image('new-facility.webp'),
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Facility');

        Storage::disk('public')->assertMissing('facilities/old.jpg');
        Storage::disk('public')->assertExists($facility->refresh()->photo_path);
    }

    public function test_admin_can_update_facility(): void
    {
        $admin = User::factory()->admin()->create();
        $facility = Facility::factory()->create(['name' => 'Old Name']);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/facilities/{$facility->id}", $this->facilityPayload(['name' => 'New Name', 'is_active' => false]))
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_unused_facility_can_be_deleted_but_used_facilities_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $unused = Facility::factory()->create();
        $assigned = Facility::factory()->create();
        $usedByBooking = Facility::factory()->create();
        $room = Room::factory()->create();
        $booking = Booking::factory()->create();

        $room->facilities()->attach($assigned->id, ['quantity' => 1]);
        $booking->facilities()->attach($usedByBooking->id, ['quantity' => 1]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/facilities/{$assigned->id}")
            ->assertStatus(409);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/facilities/{$usedByBooking->id}")
            ->assertStatus(409);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/facilities/{$unused->id}")
            ->assertOk();

        $this->assertDatabaseMissing('facilities', ['id' => $unused->id]);
    }

    public function test_facility_response_does_not_expose_internal_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $facility = Facility::factory()->create(['photo_path' => 'facilities/test.jpg']);

        $this->actingAs($admin)
            ->getJson("/api/v1/admin/facilities/{$facility->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.photo_path')
            ->assertJsonStructure(['data' => ['id', 'name', 'photo_url']]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function facilityPayload(array $overrides = []): array
    {
        return [
            'name' => 'Facility '.fake()->unique()->numberBetween(100, 999),
            'description' => 'Deskripsi fasilitas',
            'global_stock' => 10,
            'condition' => 'good',
            'is_active' => true,
            ...$overrides,
        ];
    }
}
