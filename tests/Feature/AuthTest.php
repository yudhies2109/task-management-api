<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;
use App\Models\RefreshToken;
use App\Services\Auth\RefreshTokenService;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dijalankan sebelum RefreshDatabase melakukan migration.
     */
    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');

        $database = config(
            "database.connections.{$connection}.database"
        );

        // Hentikan test jika konfigurasi tidak menunjuk database testing.
        if (! app()->environment('testing') || $database !== 'testing') {
            throw new RuntimeException(
                'Test dibatalkan: wajib memakai environment dan database testing.'
            );
        }
    }

    /**
     * Register valid harus menyimpan akun dan melakukan hashing password.
     */
    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'User Test',
            'email' => 'test@example.com',
            'password' => 'Belajar123!',
            'password_confirmation' => 'Belajar123!',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Registrasi berhasil.')
            ->assertJsonPath('data.email', 'test@example.com')
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertTrue(
            Hash::check('Belajar123!', $user->password)
        );
    }

    /**
     * Input kosong harus ditolak tanpa membuat akun.
     */
    public function test_registration_requires_name_email_and_password(): void
    {
        $this->postJson('/api/v1/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Email yang sudah digunakan tidak boleh didaftarkan lagi.
     */
    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $this->postJson('/api/v1/register', [
            'name' => 'User Baru',
            'email' => 'existing@example.com',
            'password' => 'Belajar123!',
            'password_confirmation' => 'Belajar123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    /**
     * Endpoint me harus menolak request tanpa JWT.
     */
    public function test_me_rejects_request_without_token(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    /**
     * Login valid menghasilkan JWT dan menyimpan refresh token.
     */
    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Belajar123!',
        ]);

        $response = $this->withHeaders([
            'Origin' => config('tokens.frontend_origin'),
        ])->postJson('/api/v1/login', [
            'email' => 'login@example.com',
            'password' => 'Belajar123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Login berhasil.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'expires_in',
                    'user' => ['id', 'name', 'email'],
                ],
            ])
            ->assertCookie('refresh_token');

        $this->assertNotEmpty($response->json('data.access_token'));
        $this->assertDatabaseCount('refresh_tokens', 1);
    }

    /**
     * Password salah harus ditolak tanpa menerbitkan refresh token.
     */
    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Belajar123!',
        ]);

        $this->withHeaders([
            'Origin' => config('tokens.frontend_origin'),
        ])->postJson('/api/v1/login', [
            'email' => 'login@example.com',
            'password' => 'PasswordSalah123!',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Email atau password salah.')
            ->assertJsonMissingPath('data.access_token');

        $this->assertDatabaseCount('refresh_tokens', 0);
    }

    /**
     * Login dari origin yang tidak diizinkan harus ditolak.
     */
    public function test_login_rejects_untrusted_origin(): void
    {
        $this->withHeaders([
            'Origin' => 'https://untrusted.example',
        ])->postJson('/api/v1/login', [
            'email' => 'login@example.com',
            'password' => 'Belajar123!',
        ])
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Origin request tidak diizinkan.'
            );

        $this->assertDatabaseCount('refresh_tokens', 0);
    }

    /**
     * Refresh tanpa cookie harus ditolak.
     */
    public function test_refresh_requires_cookie(): void
    {
        $this->withHeaders([
            'Origin' => config('tokens.frontend_origin'),
        ])->postJson('/api/v1/refresh-token')
            ->assertUnauthorized()
            ->assertJsonPath(
                'message',
                'Refresh token tidak valid.'
            );
    }

    /**
     * Refresh valid mengganti token dan menandai token lama digunakan.
     */
    public function test_refresh_rotates_token(): void
    {
        $user = User::factory()->create();

        $issued = app(RefreshTokenService::class)->issue($user);

        $oldToken = RefreshToken::where(
            'token_hash',
            hash('sha256', $issued['token'])
        )->firstOrFail();

        // Cookie dikirim apa adanya, sesuai route API kita
        // yang tidak memakai middleware enkripsi cookie.
        $response = $this
            ->withCredentials()
            ->withUnencryptedCookie('refresh_token', $issued['token'])
            ->withHeaders([
                'Origin' => config('tokens.frontend_origin'),
            ])
            ->postJson('/api/v1/refresh-token');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Token berhasil diperbarui.')
            ->assertJsonStructure([
                'data' => ['access_token', 'token_type', 'expires_in'],
            ])
            ->assertCookie('refresh_token');

        $this->assertNotNull($oldToken->fresh()->used_at);

        $replacement = RefreshToken::where('id', '!=', $oldToken->id)
            ->sole();

        $this->assertSame($oldToken->family_id, $replacement->family_id);
        $this->assertNull($replacement->used_at);
        $this->assertNull($replacement->revoked_at);

        $this->assertNotSame(
            $oldToken->token_hash,
            $replacement->token_hash
        );

        $this->assertTrue(
            $oldToken->expires_at->equalTo($replacement->expires_at)
        );
    }

    /**
     * Refresh token kedaluwarsa tidak boleh menghasilkan token pengganti.
     */
    public function test_refresh_rejects_expired_token(): void
    {
        $user = User::factory()->create();

        $issued = app(RefreshTokenService::class)->issue($user);

        RefreshToken::where(
            'token_hash',
            hash('sha256', $issued['token'])
        )->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this
            ->withCredentials()
            ->withUnencryptedCookie('refresh_token', $issued['token'])
            ->withHeaders([
                'Origin' => config('tokens.frontend_origin'),
            ])
            ->postJson('/api/v1/refresh-token')
            ->assertUnauthorized()
            ->assertJsonMissingPath('data.access_token');

        $this->assertDatabaseCount('refresh_tokens', 1);

        $this->assertNull(
            RefreshToken::sole()->used_at
        );
    }

    /**
     * Penggunaan ulang token lama mencabut seluruh keluarganya.
     */
    public function test_reused_refresh_token_revokes_family(): void
    {
        $user = User::factory()->create();

        $service = app(RefreshTokenService::class);
        $issued = $service->issue($user);

        // Gunakan token pertama agar terbentuk token pengganti.
        $replacement = $service->rotate($issued['token']);

        $this->assertNotNull($replacement);

        // Kirim kembali token lama yang sudah digunakan.
        $this
            ->withCredentials()
            ->withUnencryptedCookie('refresh_token', $issued['token'])
            ->withHeaders([
                'Origin' => config('tokens.frontend_origin'),
            ])
            ->postJson('/api/v1/refresh-token')
            ->assertUnauthorized();

        $this->assertDatabaseCount('refresh_tokens', 2);

        // Seluruh token keluarga ini harus sudah dicabut.
        $this->assertSame(
            0,
            RefreshToken::whereNull('revoked_at')->count()
        );

        // Token pengganti juga tidak boleh dipakai lagi.
        $this->assertNull(
            $service->rotate($replacement['token'])
        );
    }

    /**
     * Logout mencabut refresh token dan menghapus cookie.
     */
    public function test_logout_revokes_refresh_token(): void
    {
        $user = User::factory()->create();

        $service = app(RefreshTokenService::class);
        $issued = $service->issue($user);

        $this
            ->withCredentials()
            ->withUnencryptedCookie('refresh_token', $issued['token'])
            ->withHeaders([
                'Origin' => config('tokens.frontend_origin'),
            ])
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout berhasil.')
            ->assertCookieExpired('refresh_token');

        $this->assertNotNull(
            RefreshToken::sole()->revoked_at
        );

        // Token tetap ditolak meskipun seseorang menyimpan salinannya.
        $this->assertNull(
            $service->rotate($issued['token'])
        );
    }
}
