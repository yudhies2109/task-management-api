<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Pengguna yang sudah login boleh membuka daftar task.
     * Data daftar tetap harus difilter pada query controller.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Admin atau pemilik boleh melihat detail task.
     */
    public function view(User $user, Task $task): bool
    {
        return $user->isAdmin() || $user->id === $task->user_id;
    }

    /**
     * Pengguna yang sudah login boleh membuat task.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Admin atau pemilik boleh mengubah task.
     */
    public function update(User $user, Task $task): bool
    {
        return $user->isAdmin() || $user->id === $task->user_id;
    }

    /**
     * Admin atau pemilik boleh menghapus task secara soft delete.
     */
    public function delete(User $user, Task $task): bool
    {
        return $user->isAdmin() || $user->id === $task->user_id;
    }

    /**
     * Hanya Admin yang boleh memulihkan task.
     */
    public function restore(User $user, Task $task): bool
    {
        return $user->isAdmin();
    }

    /**
     * Penghapusan permanen belum diizinkan.
     */
    public function forceDelete(User $user, Task $task): bool
    {
        return false;
    }
}
