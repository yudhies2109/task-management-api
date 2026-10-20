<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use RuntimeException;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mencegah pengujian menghapus data database aplikasi.
     */
    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if (! app()->environment('testing') || $database !== 'testing') {
            throw new RuntimeException(
                'Pengujian hanya boleh menggunakan database testing.'
            );
        }
    }

    public function test_admin_endpoint_rejects_request_without_token(): void
    {
        $this->getJson('/api/v1/admin/check')
            ->assertUnauthorized();
    }

    public function test_admin_endpoint_rejects_regular_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/admin/check')
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Akses hanya diizinkan untuk admin.'
            );
    }

    public function test_admin_endpoint_accepts_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->withToken($this->tokenFor($admin))
            ->getJson('/api/v1/admin/check')
            ->assertOk()
            ->assertJsonPath('message', 'Akses admin berhasil.');
    }

    public function test_registration_cannot_assign_admin_role(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'User Baru',
            'email' => 'newuser@example.com',
            'password' => 'Belajar123!',
            'password_confirmation' => 'Belajar123!',
            'role' => 'admin',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseMissing('users', [
            'email' => 'newuser@example.com',
        ]);
    }

    public function test_registration_creates_regular_user(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'User Baru',
            'email' => 'newuser@example.com',
            'password' => 'Belajar123!',
            'password_confirmation' => 'Belajar123!',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'role' => 'user',
        ]);
    }

    /**
     * Membuat JWT, lalu mengosongkan guard agar request menguji token.
     */
    private function tokenFor(User $user): string
    {
        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');

        $token = $guard->login($user);

        Auth::forgetGuards();

        return $token;
    }
}
