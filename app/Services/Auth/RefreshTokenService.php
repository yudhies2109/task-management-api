<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Str;
use App\Models\RefreshToken;
use Illuminate\Support\Facades\DB;

class RefreshTokenService
{
    /**
     * Membuat refresh token untuk satu sesi login baru.
     *
     * @return array{token: string, expires_at: \Illuminate\Support\Carbon}
     */
    public function issue(User $user): array
    {
        // Token acak yang nanti diberikan kepada client.
        $plainToken = bin2hex(random_bytes(32));

        // Menghitung batas waktu token dapat digunakan.
        $expiresAt = now()->addDays(
            config('tokens.refresh_ttl_days')
        );

        // Database hanya menyimpan hash, bukan token aslinya.
        $user->refreshTokens()->create([
            'family_id' => (string) Str::uuid(),
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
        ]);

        // Token asli dikembalikan agar bisa diterbitkan ke client.
        return [
            'token' => $plainToken,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Mengganti refresh token yang valid.
     * Mengembalikan null jika token ditolak.
     *
     * @return array|null
     */
    public function rotate(string $plainToken): ?array
    {
        // Cari catatan melalui hash; token asli tidak disimpan.
        $tokenHash = hash('sha256', $plainToken);

        $existing = RefreshToken::where('token_hash', $tokenHash)->first();

        if (! $existing) {
            return null;
        }

        return DB::transaction(function () use ($existing, $tokenHash) {
            // Kunci pengguna untuk mengurutkan proses refresh miliknya.
            // Request bersamaan harus menunggu transaksi ini selesai.
            $user = User::whereKey($existing->user_id)
                ->lockForUpdate()
                ->first();

            if (! $user) {
                return null;
            }

            // Baca ulang token setelah mendapatkan kunci.
            $current = RefreshToken::where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (! $current) {
                return null;
            }

            // Token yang sudah dipakai muncul kembali:
            // cabut seluruh rangkaian token dari login tersebut.
            if ($current->used_at !== null) {
                RefreshToken::where('family_id', $current->family_id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);

                // Return, bukan throw, agar pencabutan tetap di-commit.
                return null;
            }

            // Tolak token yang dicabut atau kedaluwarsa.
            if (
                $current->revoked_at !== null ||
                $current->expires_at->isPast()
            ) {
                return null;
            }

            // Token lama hanya boleh dipakai sekali.
            $current->update(['used_at' => now()]);

            // Buat token pengganti yang baru dan acak.
            $newPlainToken = bin2hex(random_bytes(32));

            $user->refreshTokens()->create([
                // Tetap berada dalam keluarga login yang sama.
                'family_id' => $current->family_id,
                'token_hash' => hash('sha256', $newPlainToken),

                // Pertahankan batas akhir keluarga token:
                // refresh tidak memperpanjang login tanpa batas.
                'expires_at' => $current->expires_at,
            ]);

            return [
                'user' => $user,
                'token' => $newPlainToken,
                'expires_at' => $current->expires_at,
            ];
        });
    }
}
