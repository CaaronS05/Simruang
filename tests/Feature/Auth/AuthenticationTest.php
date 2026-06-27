<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth:sanctum', 'active', 'role:admin'])->get('/_test/role/admin', fn () => response()->json([
            'success' => true,
            'message' => 'Admin access granted.',
            'data' => (object) [],
        ]));
    }

    public function test_student_can_register_using_student_petra_email(): void
    {
        $response = $this->spaPostJson('/api/v1/auth/register', $this->validRegisterPayload([
            'email' => 'student@student.petra.ac.id',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'student@student.petra.ac.id')
            ->assertJsonPath('data.role', UserRole::STUDENT->value);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'student@student.petra.ac.id',
            'role' => UserRole::STUDENT->value,
            'is_active' => true,
        ]);
    }

    public function test_user_can_register_using_petra_email(): void
    {
        $response = $this->spaPostJson('/api/v1/auth/register', $this->validRegisterPayload([
            'email' => 'user@petra.ac.id',
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'user@petra.ac.id');
    }

    public function test_non_campus_email_is_rejected(): void
    {
        $response = $this->spaPostJson('/api/v1/auth/register', $this->validRegisterPayload([
            'email' => 'user@example.com',
        ]));

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('email');
    }

    public function test_register_cannot_assign_admin_role(): void
    {
        $response = $this->spaPostJson('/api/v1/auth/register', $this->validRegisterPayload([
            'email' => 'role@student.petra.ac.id',
            'role' => UserRole::ADMIN->value,
            'is_active' => false,
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.role', UserRole::STUDENT->value)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('users', [
            'email' => 'role@student.petra.ac.id',
            'role' => UserRole::STUDENT->value,
            'is_active' => true,
        ]);
    }

    public function test_duplicate_campus_id_is_rejected(): void
    {
        User::factory()->create(['campus_id' => 'CAMPUS-001']);

        $response = $this->spaPostJson('/api/v1/auth/register', $this->validRegisterPayload([
            'campus_id' => 'CAMPUS-001',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('campus_id');
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'duplicate@student.petra.ac.id']);

        $response = $this->spaPostJson('/api/v1/auth/register', $this->validRegisterPayload([
            'email' => 'duplicate@student.petra.ac.id',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_password_confirmation_must_match(): void
    {
        $response = $this->spaPostJson('/api/v1/auth/register', $this->validRegisterPayload([
            'password_confirmation' => 'DifferentPassword123!',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_active_user_can_login(): void
    {
        $user = User::factory()->create(['email' => 'login@student.petra.ac.id']);

        $response = $this->spaPostJson('/api/v1/auth/login', [
            'email' => 'LOGIN@student.petra.ac.id',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id);

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'wrong@student.petra.ac.id']);

        $response = $this->spaPostJson('/api/v1/auth/login', [
            'email' => 'wrong@student.petra.ac.id',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create(['email' => 'inactive@student.petra.ac.id']);

        $response = $this->spaPostJson('/api/v1/auth/login', [
            'email' => 'inactive@student.petra.ac.id',
            'password' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_session_is_regenerated_after_login(): void
    {
        User::factory()->create(['email' => 'session@student.petra.ac.id']);
        $this->withSession(['before_login' => true]);
        $oldSessionId = session()->getId();

        $this->spaPostJson('/api/v1/auth/login', [
            'email' => 'session@student.petra.ac.id',
            'password' => 'password',
        ])->assertOk();

        $this->assertNotSame($oldSessionId, session()->getId());
    }

    public function test_authenticated_user_can_access_me(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->spaGetJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_guest_cannot_access_me(): void
    {
        $response = $this->spaGetJson('/api/v1/auth/me');

        $response->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_logout_ends_session(): void
    {
        User::factory()->create(['email' => 'logout@student.petra.ac.id']);

        $this->spaPostJson('/api/v1/auth/login', [
            'email' => 'logout@student.petra.ac.id',
            'password' => 'password',
        ])->assertOk();

        $this->spaPostJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        Auth::forgetGuards();

        $this->spaGetJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_student_is_rejected_by_admin_role_middleware(): void
    {
        $student = User::factory()->create();

        $response = $this->actingAs($student)->getJson('/_test/role/admin');

        $response->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_pass_admin_role_middleware(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/_test/role/admin');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_authenticated_user_disabled_after_login_is_rejected_from_protected_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $response = $this->spaGetJson('/api/v1/auth/me');

        $response->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_user_response_does_not_contain_sensitive_fields(): void
    {
        $response = $this->spaPostJson('/api/v1/auth/register', $this->validRegisterPayload([
            'email' => 'safe@student.petra.ac.id',
        ]));

        $response->assertCreated()
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        RateLimiter::clear('throttle@student.petra.ac.id|127.0.0.1');
        User::factory()->create(['email' => 'throttle@student.petra.ac.id']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->spaPostJson('/api/v1/auth/login', [
                'email' => 'throttle@student.petra.ac.id',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->spaPostJson('/api/v1/auth/login', [
            'email' => 'throttle@student.petra.ac.id',
            'password' => 'wrong-password',
        ])->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_cors_config_does_not_use_wildcard_origin_with_credentials(): void
    {
        $this->assertTrue(config('cors.supports_credentials'));
        $this->assertSame(['http://127.0.0.1:8000'], config('cors.allowed_origins'));
        $this->assertNotContains('*', config('cors.allowed_origins'));
        $this->assertContains('api/*', config('cors.paths'));
        $this->assertContains('sanctum/csrf-cookie', config('cors.paths'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validRegisterPayload(array $overrides = []): array
    {
        return [
            'name' => 'Student User',
            'campus_id' => 'STU-'.fake()->unique()->numerify('#####'),
            'email' => 'student'.fake()->unique()->numberBetween(1000, 9999).'@student.petra.ac.id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function spaPostJson(string $uri, array $payload = []): TestResponse
    {
        return $this->withHeaders($this->spaHeaders())->postJson($uri, $payload);
    }

    private function spaGetJson(string $uri): TestResponse
    {
        return $this->withHeaders($this->spaHeaders())->getJson($uri);
    }

    /**
     * @return array<string, string>
     */
    private function spaHeaders(): array
    {
        return [
            'Origin' => 'http://127.0.0.1:8000',
            'Referer' => 'http://127.0.0.1:8000',
        ];
    }
}
