<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    /**
     * Menampilkan daftar task sesuai hak akses pengguna.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Task::class);

        $user = $request->user('api');

        $query = Task::query();

        // User hanya melihat task sendiri; Admin dapat melihat semuanya.
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $tasks = $query
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json($tasks);
    }

    /**
     * Membuat task dengan pemilik pengguna yang sedang login.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        Gate::authorize('create', Task::class);

        $task = $request->user('api')
            ->tasks()
            ->create($request->validated());

        // Memuat nilai default status dan priority dari database.
        $task->refresh();

        return response()->json([
            'message' => 'Task berhasil dibuat.',
            'data' => $task,
        ], 201);
    }

    /**
     * Menampilkan detail task.
     */
    public function show(Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        return response()->json([
            'data' => $task,
        ]);
    }

    /**
     * Memperbarui task.
     */
    public function update(
        UpdateTaskRequest $request,
        Task $task
    ): JsonResponse {
        Gate::authorize('update', $task);

        $task->update($request->validated());

        return response()->json([
            'message' => 'Task berhasil diperbarui.',
            'data' => $task,
        ]);
    }

    /**
     * Menghapus task secara soft delete.
     */
    public function destroy(Task $task): JsonResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return response()->json([
            'message' => 'Task berhasil dihapus.',
        ]);
    }
}
