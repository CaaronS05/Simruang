<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'role' => ['nullable', 'in:admin,student'],
            'is_active' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'in:name,campus_id,email,created_at'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('campus_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($validated['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy($validated['sort_by'] ?? 'created_at', $validated['sort_direction'] ?? 'desc')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => UserResource::collection($users)->resolve(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
            'links' => [
                'first' => $users->url(1),
                'last' => $users->url($users->lastPage()),
                'prev' => $users->previousPageUrl(),
                'next' => $users->nextPageUrl(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => new UserResource($user),
        ]);
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        if ($request->user()->is($user) && $request->boolean('is_active') === false) {
            return response()->json([
                'success' => false,
                'message' => 'Admin tidak boleh menonaktifkan akun sendiri.',
                'errors' => (object) [],
            ], 403);
        }

        $user->update([
            'is_active' => $request->boolean('is_active'),
            'role' => $user->role ?? UserRole::STUDENT,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status user berhasil diperbarui.',
            'data' => new UserResource($user->refresh()),
        ]);
    }
}
