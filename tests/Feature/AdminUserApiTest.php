<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_list_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/users')
            ->assertForbidden();
    }

    public function test_admin_can_list_users_and_search_filter_sort(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Alice Student', 'campus_id' => 'S001', 'email' => 'alice@student.petra.ac.id', 'role' => UserRole::STUDENT, 'is_active' => true]);
        User::factory()->inactive()->create(['name' => 'Bob Student', 'campus_id' => 'S002', 'email' => 'bob@student.petra.ac.id', 'role' => UserRole::STUDENT]);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/users?search=Alice&role=student&is_active=1&sort_by=email&sort_direction=asc')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'alice@student.petra.ac.id');
    }

    public function test_admin_can_view_user_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();

        $this->actingAs($admin)
            ->getJson("/api/v1/admin/users/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $student->id)
            ->assertJsonPath('data.role', UserRole::STUDENT->value);
    }

    public function test_admin_can_deactivate_and_reactivate_student(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$student->id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', ['id' => $student->id, 'is_active' => false]);

        $this->actingAs($student->refresh())
            ->getJson('/api/v1/rooms')
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$student->id}/status", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$admin->id}/status", ['is_active' => false])
            ->assertForbidden();

        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_status_endpoint_does_not_allow_role_modification(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create(['role' => UserRole::STUDENT]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$student->id}/status", [
                'is_active' => false,
                'role' => UserRole::ADMIN->value,
                'password' => 'ChangedPassword123!',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', UserRole::STUDENT->value);

        $this->assertSame(UserRole::STUDENT, $student->refresh()->role);
    }

    public function test_user_response_excludes_sensitive_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();

        $this->actingAs($admin)
            ->getJson("/api/v1/admin/users/{$student->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token')
            ->assertJsonStructure(['data' => ['id', 'name', 'campus_id', 'email', 'role', 'is_active', 'created_at', 'updated_at']]);
    }
}
