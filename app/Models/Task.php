<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title',
    'description',
    'status',
    'priority',
    'due_date',
])]
class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    /**
     * Pengguna yang memiliki task ini.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}