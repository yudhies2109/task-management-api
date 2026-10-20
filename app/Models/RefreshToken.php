<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Kolom yang boleh diisi melalui create() atau update().
#[Fillable([
    'user_id',
    'family_id',
    'token_hash',
    'expires_at',
    'used_at',
    'revoked_at',
])]

// Menyembunyikan hash token dari hasil serialisasi JSON.
#[Hidden(['token_hash'])]
class RefreshToken extends Model
{
    /**
     * Mengubah nilai tanggal database menjadi objek datetime.
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Setiap refresh token dimiliki oleh satu pengguna.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}